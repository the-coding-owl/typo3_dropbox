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

/**
 * Created by PhpStorm.
 * User: kevin
 * Date: 01.04.18
 * Time: 13:47
 */

namespace TheCodingOwl\Typo3Dropbox\Controller;


use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Fluid\View\StandaloneView;
use TYPO3Fluid\Fluid\View\ViewInterface;

class DropboxController
{
    /**
     * Render the infobox
     *
     * @return string
     */
    public function renderInfoboxAction(): string
    {
        return $this->createView('RenderInfobox')->render();
    }

    /**
     * Render the connect button
     *
     * @return string
     * @throws \TYPO3\CMS\Backend\Routing\Exception\RouteNotFoundException
     */
    public function renderConnectButtonAction(): string
    {
        $pageRenderer = GeneralUtility::makeInstance(PageRenderer::class);
        $pageRenderer->loadRequireJsModule('TYPO3/CMS/Typo3Dropbox/Connect');
        $view = $this->createView('RenderConnectButton');
        $uriBuilder = GeneralUtility::makeInstance(UriBuilder::class);
        $returnUri = $uriBuilder->buildUriFromRoute('dropbox_authenticate');
        $view->assign('returnUri', $returnUri);
        return $view->render();
    }

    public function authenticateAction(ServerRequestInterface $request, ResponseInterface $response)
    {

    }

    public function checkAuthenticationAction(ServerRequestInterface $request, ResponseInterface $response)
    {

    }

    /**
     * Create a new view with the given template name
     *
     * @param string $templateName
     * @return ViewInterface
     */
    protected function createView(string $templateName): ViewInterface
    {
        $view = GeneralUtility::makeInstance(StandaloneView::class);
        $view->setFormat('html');
        $view->setTemplate($templateName);
        $view->setTemplateRootPaths([
            'EXT:typo3_dropbox/Resources/Private/Templates/'
        ]);
        $view->setLayoutRootPaths([
            'EXT:typo3_dropbox/Resources/Private/Layouts/'
        ]);
        $view->setPartialRootPaths([
            'EXT:typo3_dropbox/Resources/Private/Partials/'
        ]);
        return $view;
    }
}