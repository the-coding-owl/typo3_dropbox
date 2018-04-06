<?php
/**
 * This file is part of typo3-dropbox.
 *
 * typo3-dropbox is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * typo3-dropbox is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with typo3-dropbox.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace TheCodingOwl\Typo3Dropbox\Resource\Driver;

use Kunnu\Dropbox\Dropbox;
use Kunnu\Dropbox\DropboxApp;
use Kunnu\Dropbox\DropboxFile;
use Kunnu\Dropbox\Exceptions\DropboxClientException;
use Kunnu\Dropbox\Models\FileMetadata;
use Kunnu\Dropbox\Models\FolderMetadata;
use TYPO3\CMS\Core\Resource\Driver\AbstractDriver;
use TYPO3\CMS\Core\Resource\ResourceStorage;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Class DropboxDriver
 *
 * @package TheCodingOwl\Typo3Dropbox\Resource\Driver
 * @author Kevin Ditscheid <kevinditscheid@gmail.com>
 */
class DropboxDriver extends AbstractDriver
{
    /**
     * @var Dropbox
     */
    protected $dropbox;

    public function __construct(array $configuration = [])
    {
        parent::__construct($configuration);
        $this->capabilities = ResourceStorage::CAPABILITY_BROWSABLE
            | ResourceStorage::CAPABILITY_PUBLIC
            | ResourceStorage::CAPABILITY_WRITABLE;
    }

    /**
     * Makes sure the path given as parameter is valid
     *
     * @param string $filePath The file path (most times filePath)
     * @return string
     */
    protected function canonicalizeAndCheckFilePath($filePath)
    {
        $filePath = \str_replace($this->storageUid . ':', '', $filePath);
        $newPath = '/';
        if ($filePath !== '/') {
            $pathParts = \explode('/', trim($filePath, '/'));
            $newPathParts = [];
            for ($iterator = 0; $iterator < count($pathParts); $iterator++) {
                switch ($pathParts[$iterator]) {
                    case '.':
                    case '':
                        // ignore these parts of the parts
                        break;
                    case '..':
                        if ($iterator >= 1) {
                            // remove the last element from the new path, because we move up in the directory structure
                            array_pop($newPathParts);
                        }
                        break;
                    default:
                        $newPathParts[] = $pathParts[$iterator];
                        break;
                }
            }
            $newPath = '/' . \implode('/', $newPathParts);
        }
        return $newPath;
    }

    /**
     * Makes sure the identifier given as parameter is valid
     *
     * @param string $fileIdentifier The file Identifier
     * @return string
     * @throws \TYPO3\CMS\Core\Resource\Exception\InvalidPathException
     */
    protected function canonicalizeAndCheckFileIdentifier($fileIdentifier)
    {
        $path = $this->canonicalizeAndCheckFilePath($fileIdentifier);
        return $this->storageUid . ':' . $path;
    }

    /**
     * Makes sure the identifier given as parameter is valid
     *
     * @param string $folderIdentifier The folder identifier
     * @return string
     */
    protected function canonicalizeAndCheckFolderIdentifier($folderIdentifier)
    {
        return $this->canonicalizeAndCheckFileIdentifier($folderIdentifier);
    }

    /**
     * Processes the configuration for this driver.
     */
    public function processConfiguration()
    {
    }

    /**
     * Initializes this object. This is called by the storage after the driver
     * has been attached.
     */
    public function initialize()
    {
        $dropboxApp = new DropboxApp($this->configuration['appid'],
            $this->configuration['secret'], $this->configuration['token']);
        $this->dropbox = new Dropbox($dropboxApp);
    }

    /**
     * Merges the capabilities merged by the user at the storage
     * configuration into the actual capabilities of the driver
     * and returns the result.
     *
     * @param int $capabilities
     * @return int
     */
    public function mergeConfigurationCapabilities($capabilities)
    {
        $this->capabilities &= $capabilities;
        return $this->capabilities;
    }

    /**
     * Returns the identifier of the root level folder of the storage.
     *
     * @return string
     */
    public function getRootLevelFolder()
    {
        return '/';
    }

    /**
     * Returns the identifier of the default folder new files should be put into.
     *
     * @return string
     */
    public function getDefaultFolder()
    {
        return '/';
    }

    /**
     * Returns the identifier of the folder the file resides in
     *
     * @param string $fileIdentifier
     * @return string
     */
    public function getParentFolderIdentifierOfIdentifier($fileIdentifier)
    {
        $canonicalizedPath = $this->canonicalizeAndCheckFilePath($fileIdentifier);
        if($canonicalizedPath === '/'){
            $canonicalizedIdentifier = $this->canonicalizeAndCheckFileIdentifier('/');
        } else {
            $canonicalizedIdentifier = $this->canonicalizeAndCheckFileIdentifier($this->dropbox->getMetadata($canonicalizedPath)->getPathLower());
        }
        return $canonicalizedIdentifier;
    }

    /**
     * Returns the public URL to a file.
     * Either fully qualified URL or relative to PATH_site (rawurlencoded).
     *
     * @param string $identifier
     * @return string
     */
    public function getPublicUrl($identifier)
    {
        $response = $this->dropbox->getTemporaryLink($this->canonicalizeAndCheckFilePath($identifier));
        return $response->getLink();
    }

    /**
     * Creates a folder, within a parent folder.
     * If no parent folder is given, a root level folder will be created
     *
     * @param string $newFolderName
     * @param string $parentFolderIdentifier
     * @param bool $recursive
     * @return string the Identifier of the new folder
     */
    public function createFolder($newFolderName, $parentFolderIdentifier = '', $recursive = false)
    {
        $path = $parentFolderIdentifier . $newFolderName;
        if ($parentFolderIdentifier === '') {
            $path = $this->getRootLevelFolder() . $newFolderName;
        }
        $response = $this->dropbox->createFolder($this->canonicalizeAndCheckFilePath($path));
        return $response->getPathLower();
    }

    /**
     * Renames a folder in this storage.
     *
     * @param string $folderIdentifier
     * @param string $newName
     * @return array A map of old to new file identifiers of all affected resources
     */
    public function renameFolder($folderIdentifier, $newName)
    {
        $newIdentifier = $this->renameFile($folderIdentifier, $newName);
        return [$this->canonicalizeAndCheckFolderIdentifier($folderIdentifier) => $newIdentifier];
    }

    /**
     * Removes a folder in filesystem.
     *
     * @param string $folderIdentifier
     * @param bool $deleteRecursively
     * @return bool
     */
    public function deleteFolder($folderIdentifier, $deleteRecursively = false)
    {
        return $this->deleteFile($folderIdentifier);
    }

    /**
     * Checks if a file exists.
     *
     * @param string $fileIdentifier
     * @return bool
     */
    public function fileExists($fileIdentifier)
    {
        if ($fileIdentifier === '/') {
            $fileExists = true;
        } else {
            try {
                $this->dropbox->getMetadata($this->canonicalizeAndCheckFilePath($fileIdentifier));
                $fileExists = true;
            } catch (DropboxClientException $e) {
                if ($e->getCode() === 409) {
                    // file not found
                } else {
                    throw $e;
                }
            }
        }
        return $fileExists;
    }

    /**
     * Checks if a folder exists.
     *
     * @param string $folderIdentifier
     * @return bool
     */
    public function folderExists($folderIdentifier)
    {
        return $this->fileExists($folderIdentifier);
    }

    /**
     * Checks if a folder contains files and (if supported) other folders.
     *
     * @param string $folderIdentifier
     * @return bool TRUE if there are no files and folders within $folder
     */
    public function isFolderEmpty($folderIdentifier)
    {
        $response = $this->dropbox->listFolder($this->canonicalizeAndCheckFilePath($folderIdentifier));
        return $response->hasMoreItems();
    }

    /**
     * Adds a file from the local server hard disk to a given path in TYPO3s
     * virtual file system. This assumes that the local file exists, so no
     * further check is done here! After a successful the original file must
     * not exist anymore.
     *
     * @param string $localFilePath (within PATH_site)
     * @param string $targetFolderIdentifier
     * @param string $newFileName optional, if not given original name is used
     * @param bool $removeOriginal if set the original file will be removed
     *                                after successful operation
     * @return string the identifier of the new file
     */
    public function addFile($localFilePath, $targetFolderIdentifier, $newFileName = '', $removeOriginal = true)
    {
        $response = $this->dropbox->upload(new DropboxFile($localFilePath),
            $this->canonicalizeAndCheckFilePath($targetFolderIdentifier . '/' . $newFileName));
        $newIdentifier = '';
        if ($response->getId()) {
            $newIdentifier = $this->canonicalizeAndCheckFileIdentifier($response->getPathLower());
        }
        if ($response->getId() && $removeOriginal) {
            \unlink($localFilePath);
        }
        return $newIdentifier;
    }

    /**
     * Creates a new (empty) file and returns the identifier.
     *
     * @param string $fileName
     * @param string $parentFolderIdentifier
     * @return string
     */
    public function createFile($fileName, $parentFolderIdentifier)
    {
        $emptyFile = GeneralUtility::tempnam('dropbox_');
        $newIdentifier = $this->addFile($emptyFile, $parentFolderIdentifier, $fileName);
        \unlink($emptyFile);
        return $newIdentifier;
    }

    /**
     * Copies a file *within* the current storage.
     * Note that this is only about an inner storage copy action,
     * where a file is just copied to another folder in the same storage.
     *
     * @param string $fileIdentifier
     * @param string $targetFolderIdentifier
     * @param string $fileName
     * @return string the Identifier of the new file
     */
    public function copyFileWithinStorage($fileIdentifier, $targetFolderIdentifier, $fileName)
    {
        $response = $this->dropbox->copy(
            $this->canonicalizeAndCheckFilePath($fileIdentifier),
            $this->canonicalizeAndCheckFilePath($targetFolderIdentifier . '/' . $fileName));
        return $this->canonicalizeAndCheckFileIdentifier($response->getPathLower());
    }

    /**
     * Renames a file in this storage.
     *
     * @param string $fileIdentifier
     * @param string $newName The target path (including the file name!)
     * @return string The identifier of the file after renaming
     */
    public function renameFile($fileIdentifier, $newName)
    {
        $response = $this->dropbox->move(
            $this->canonicalizeAndCheckFilePath($fileIdentifier),
            $this->canonicalizeAndCheckFilePath($this->renameIdentifier($fileIdentifier, $newName)));
        return $this->canonicalizeAndCheckFileIdentifier($response->getPathLower());
    }

    /**
     * Replaces a file with file in local file system.
     *
     * @param string $fileIdentifier
     * @param string $localFilePath
     * @return bool TRUE if the operation succeeded
     */
    public function replaceFile($fileIdentifier, $localFilePath)
    {
        $success = false;
        if ($this->deleteFile($fileIdentifier)) {
            $newIdentifier = $this->addFile($localFilePath, $fileIdentifier, $fileIdentifier, true);
            if ($newIdentifier) {
                $success = true;
            }
        }
        return $success;
    }

    /**
     * Removes a file from the filesystem. This does not check if the file is
     * still used or if it is a bad idea to delete it for some other reason
     * this has to be taken care of in the upper layers (e.g. the Storage)!
     *
     * @param string $fileIdentifier
     * @return bool TRUE if deleting the file succeeded
     */
    public function deleteFile($fileIdentifier)
    {
        $success = false;
        $response = $this->dropbox->delete($this->canonicalizeAndCheckFilePath($fileIdentifier));
        if ($response->getId()) {
            $success = true;
        }
        return $success;
    }

    /**
     * Creates a hash for a file.
     *
     * @param string $fileIdentifier
     * @param string $hashAlgorithm The hash algorithm to use
     * @return string
     */
    public function hash($fileIdentifier, $hashAlgorithm)
    {
        // TODO: Implement hash() method.
    }

    /**
     * Moves a file *within* the current storage.
     * Note that this is only about an inner-storage move action,
     * where a file is just moved to another folder in the same storage.
     *
     * @param string $fileIdentifier
     * @param string $targetFolderIdentifier
     * @param string $newFileName
     * @return string
     */
    public function moveFileWithinStorage($fileIdentifier, $targetFolderIdentifier, $newFileName)
    {
        $response = $this->dropbox->move(
            $this->canonicalizeAndCheckFilePath($fileIdentifier),
            $this->canonicalizeAndCheckFilePath($targetFolderIdentifier . '/' . $newFileName)
        );
        return $this->canonicalizeAndCheckFileIdentifier($response->getPathLower());
    }

    /**
     * Folder equivalent to moveFileWithinStorage().
     *
     * @param string $sourceFolderIdentifier
     * @param string $targetFolderIdentifier
     * @param string $newFolderName
     * @return array All files which are affected, map of old => new file identifiers
     */
    public function moveFolderWithinStorage($sourceFolderIdentifier, $targetFolderIdentifier, $newFolderName)
    {
        $newIdentifier = $this->moveFileWithinStorage($sourceFolderIdentifier, $targetFolderIdentifier, $newFolderName);
        return [$this->canonicalizeAndCheckFolderIdentifier($sourceFolderIdentifier) => $newIdentifier];
    }

    /**
     * Folder equivalent to copyFileWithinStorage().
     *
     * @param string $sourceFolderIdentifier
     * @param string $targetFolderIdentifier
     * @param string $newFolderName
     * @return bool
     */
    public function copyFolderWithinStorage($sourceFolderIdentifier, $targetFolderIdentifier, $newFolderName)
    {
        $newIdentifier = $this->copyFileWithinStorage($sourceFolderIdentifier, $targetFolderIdentifier, $newFolderName);
        return (bool)$newIdentifier;
    }

    /**
     * Returns the contents of a file. Beware that this requires to load the
     * complete file into memory and also may require fetching the file from an
     * external location. So this might be an expensive operation (both in terms
     * of processing resources and money) for large files.
     *
     * @param string $fileIdentifier
     * @return string The file contents
     */
    public function getFileContents($fileIdentifier)
    {
        return $this->dropbox->download($this->canonicalizeAndCheckFilePath($fileIdentifier))->getContents();
    }

    /**
     * Sets the contents of a file to the specified value.
     *
     * @param string $fileIdentifier
     * @param string $contents
     * @return int The number of bytes written to the file
     */
    public function setFileContents($fileIdentifier, $contents)
    {
        $tempfile = GeneralUtility::tempnam('dropbox_');
        $success = \file_put_contents($tempfile, $contents);
        if ($success !== false) {
            $success = $this->replaceFile($fileIdentifier, $tempfile);
        }
        return $success;
    }

    /**
     * Checks if a file inside a folder exists
     *
     * @param string $fileName
     * @param string $folderIdentifier
     * @return bool
     */
    public function fileExistsInFolder($fileName, $folderIdentifier)
    {
        return $this->fileExists($folderIdentifier . '/' . $fileName);
    }

    /**
     * Checks if a folder inside a folder exists.
     *
     * @param string $folderName
     * @param string $folderIdentifier
     * @return bool
     */
    public function folderExistsInFolder($folderName, $folderIdentifier)
    {
        return $this->folderExists($folderIdentifier . '/' . $folderName);
    }

    /**
     * Returns a path to a local copy of a file for processing it. When changing the
     * file, you have to take care of replacing the current version yourself!
     *
     * @param string $fileIdentifier
     * @param bool $writable Set this to FALSE if you only need the file for read
     *                       operations. This might speed up things, e.g. by using
     *                       a cached local version. Never modify the file if you
     *                       have set this flag!
     * @return string The path to the file on the local disk
     */
    public function getFileForLocalProcessing($fileIdentifier, $writable = true)
    {
        $tempfile = GeneralUtility::tempnam('dropbox_');
        $file = $this->dropbox->download($this->canonicalizeAndCheckFilePath($fileIdentifier), $tempfile);
        return $tempfile;
    }

    /**
     * Returns the permissions of a file/folder as an array
     * (keys r, w) of boolean flags
     *
     * @param string $identifier
     * @return array
     */
    public function getPermissions($identifier)
    {
        return ['r' => true, 'w' => true];
    }

    /**
     * Directly output the contents of the file to the output
     * buffer. Should not take care of header files or flushing
     * buffer before. Will be taken care of by the Storage.
     *
     * @param string $identifier
     */
    public function dumpFileContents($identifier)
    {
        return $this->dropbox->download($this->canonicalizeAndCheckFilePath($identifier))->getContents();
    }

    /**
     * Checks if a given identifier is within a container, e.g. if
     * a file or folder is within another folder.
     * This can e.g. be used to check for web-mounts.
     *
     * Hint: this also needs to return TRUE if the given identifier
     * matches the container identifier to allow access to the root
     * folder of a filemount.
     *
     * @param string $folderIdentifier
     * @param string $identifier identifier to be checked against $folderIdentifier
     * @return bool TRUE if $content is within or matches $folderIdentifier
     */
    public function isWithin($folderIdentifier, $identifier)
    {
        $isWithin = false;
        $canonicalIdentifier = $this->canonicalizeAndCheckFileIdentifier($identifier);
        $canonicalFolderIdentifier = $this->canonicalizeAndCheckFolderIdentifier($folderIdentifier);
        if ($canonicalFolderIdentifier === $canonicalIdentifier) {
            $isWithin = true;
        } else {
            if (\strpos($canonicalIdentifier, $canonicalFolderIdentifier) === 0) {
                $isWithin = true;
            }
        }
        return $isWithin;
    }

    /**
     * Returns information about a file.
     *
     * @param string $fileIdentifier
     * @param array $propertiesToExtract Array of properties which are be extracted
     *                                   If empty all will be extracted
     * @return array
     */
    public function getFileInfoByIdentifier($fileIdentifier, array $propertiesToExtract = [])
    {
        $canonicalizedPath = $this->canonicalizeAndCheckFilePath($fileIdentifier);
        try {
            $fileMetaData = $this->dropbox->getMetadata($canonicalizedPath);
        } catch (DropboxClientException $e) {
            if ($canonicalizedPath === '/') {
                $fileMetaData = new FileMetadata([
                    'id' => 0,
                    'rev' => '',
                    'name' => '/',
                    'size' => 0,
                    'path_lower' => '/',
                    'path_display' => '/',
                    'client_modified' => 0,
                    'server_modified' => 0,
                    'has_explicit_shared_members' => null,
                    'media_info' => null,
                    'sharing_info' => null
                ]);
            } else {
                throw $e;
            }
        }
        $properties = [];
        if ($propertiesToExtract === []) {
            $propertiesToExtract = [
                'size',
                'atime',
                'mtime',
                'ctime',
                'name',
                'mimetype',
                'identifier',
                'storage',
                'identifier_hash',
                'folder_hash'
            ];
        }
        foreach ($propertiesToExtract as $property) {
            switch ($property) {
                case 'size':
                    if ($fileMetaData instanceof FileMetadata) {
                        $properties[$property] = $fileMetaData->getSize();
                    } else {
                        $properties[$property] = 0;
                    }
                    break;
                case 'atime':
                case 'mtime':
                case 'ctime':
                    if ($fileMetaData instanceof FileMetadata) {
                        $properties[$property] = $fileMetaData->getClientModified();
                    } else {
                        $properties[$property] = 0;
                    }
                    break;
                case 'name':
                    $properties[$property] = $fileMetaData->getName();
                    break;
                case 'mimetype':
                    if ($fileMetaData instanceof FolderMetadata) {
                        $properties[$property] = '';
                    } else {
                        $properties[$property] = '';
                    }
                    break;
                case 'identifier':
                    $properties[$property] = $this->canonicalizeAndCheckFileIdentifier($fileIdentifier);
                    break;
                case 'storage':
                    $properties[$property] = $this->storageUid;
                    break;
                case 'identifier_hash':
                    $properties[$property] = $this->hashIdentifier($fileIdentifier);
                    break;
                case 'folder_hash':
                    $identifierParts = \explode('/', $this->canonicalizeAndCheckFileIdentifier($fileIdentifier));
                    array_pop($identifierParts);
                    $properties[$property] = $this->hashIdentifier(implode('/', $identifierParts));
                    break;
                default:
                    throw new \InvalidArgumentException(sprintf('The information "%s" is not available.', $property),
                        1476047422);
            }
        }
        return $properties;
    }

    /**
     * Returns information about a file.
     *
     * @param string $folderIdentifier
     * @return array
     */
    public function getFolderInfoByIdentifier($folderIdentifier)
    {
        return $this->getFileInfoByIdentifier($folderIdentifier);
    }

    /**
     * Returns the identifier of a file inside the folder
     *
     * @param string $fileName
     * @param string $folderIdentifier
     * @return string file identifier
     */
    public function getFileInFolder($fileName, $folderIdentifier)
    {
        return $this->canonicalizeAndCheckFileIdentifier($folderIdentifier . '/' . $fileName);
    }

    /**
     * Returns a list of files inside the specified path
     *
     * @param string $folderIdentifier
     * @param int $start
     * @param int $numberOfItems
     * @param bool $recursive
     * @param array $filenameFilterCallbacks callbacks for filtering the items
     * @param string $sort Property name used to sort the items.
     *                     Among them may be: '' (empty, no sorting), name,
     *                     fileext, size, tstamp and rw.
     *                     If a driver does not support the given property, it
     *                     should fall back to "name".
     * @param bool $sortRev TRUE to indicate reverse sorting (last to first)
     * @return array of FileIdentifiers
     */
    public function getFilesInFolder(
        $folderIdentifier,
        $start = 0,
        $numberOfItems = 0,
        $recursive = false,
        array $filenameFilterCallbacks = [],
        $sort = '',
        $sortRev = false
    ) {
        $response = $this->dropbox->listFolder($this->canonicalizeAndCheckFolderPath($folderIdentifier));
        $iterator = 0;
        $listedFiles = [];
        foreach ($response->getItems() as $item) {
            if ($item instanceof FileMetadata) {
                if ($iterator >= $start && $iterator < $start + $numberOfItems) {
                    $listedFiles[] = $this->canonicalizeAndCheckFileIdentifier($item->getPathLower());
                }
                $iterator++;
            }
        }
        return $listedFiles;
    }

    /**
     * Returns the identifier of a folder inside the folder
     *
     * @param string $folderName The name of the target folder
     * @param string $folderIdentifier
     * @return string folder identifier
     */
    public function getFolderInFolder($folderName, $folderIdentifier)
    {
        return $this->getFileInFolder($folderName, $folderIdentifier);
    }

    /**
     * Returns a list of folders inside the specified path
     *
     * @param string $folderIdentifier
     * @param int $start
     * @param int $numberOfItems
     * @param bool $recursive
     * @param array $folderNameFilterCallbacks callbacks for filtering the items
     * @param string $sort Property name used to sort the items.
     *                     Among them may be: '' (empty, no sorting), name,
     *                     fileext, size, tstamp and rw.
     *                     If a driver does not support the given property, it
     *                     should fall back to "name".
     * @param bool $sortRev TRUE to indicate reverse sorting (last to first)
     * @return array of Folder Identifier
     */
    public function getFoldersInFolder(
        $folderIdentifier,
        $start = 0,
        $numberOfItems = 0,
        $recursive = false,
        array $folderNameFilterCallbacks = [],
        $sort = '',
        $sortRev = false
    ) {
        $response = $this->dropbox->listFolder($this->canonicalizeAndCheckFilePath($folderIdentifier));
        $iterator = 0;
        $listedFolders = [];
        foreach ($response->getItems() as $item) {
            if ($item instanceof FolderMetadata) {
                if ($iterator >= $start && $iterator < $start + $numberOfItems) {
                    $listedFolders[] = $this->canonicalizeAndCheckFileIdentifier($item->getPathLower());
                }
                $iterator++;
            }
        }
        return $listedFolders;
    }

    /**
     * Returns the number of files inside the specified path
     *
     * @param string $folderIdentifier
     * @param bool $recursive
     * @param array $filenameFilterCallbacks callbacks for filtering the items
     * @return int Number of files in folder
     */
    public function countFilesInFolder($folderIdentifier, $recursive = false, array $filenameFilterCallbacks = [])
    {
        return count($this->getFilesInFolder($folderIdentifier, 0, 0, $recursive, $filenameFilterCallbacks));
    }

    /**
     * Returns the number of folders inside the specified path
     *
     * @param string $folderIdentifier
     * @param bool $recursive
     * @param array $folderNameFilterCallbacks callbacks for filtering the items
     * @return int Number of folders in folder
     */
    public function countFoldersInFolder($folderIdentifier, $recursive = false, array $folderNameFilterCallbacks = [])
    {
        return count($this->getFoldersInFolder($folderIdentifier, 0, 0, $recursive, $folderNameFilterCallbacks));
    }

    /**
     * Rename the last file or folder in the given identifier
     *
     * @param $identifier The identifier to rename
     * @param $newName The new name of the file or folder identifier
     *
     * @return string
     */
    protected function renameIdentifier($identifier, $newName)
    {
        $canonicalizedIdentifier = $this->canonicalizeAndCheckFolderIdentifier($identifier);
        $identifierParts = \explode('/', $canonicalizedIdentifier);
        array_pop($identifierParts);
        return $this->canonicalizeAndCheckFolderIdentifier(\implode('/', $newName));
    }
}