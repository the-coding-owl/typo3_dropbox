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

defined('TYPO3_MODE') or die('Access denied!');

return [
    'dropbox_authenticate' => [
        'path' => '/typo3_dropbox/authenticate',
        'access' => 'public',
        'target' => \TheCodingOwl\Typo3Dropbox\Controller\DropboxController::class . '::authenticateAction'
    ]
];