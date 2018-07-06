<?php
namespace JWeiland\Maps2\Form\Element;

/*
 * This file is part of the maps2 project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

use JWeiland\Maps2\Configuration\ExtConf;
use JWeiland\Maps2\Domain\Model\PoiCollection;
use JWeiland\Maps2\Domain\Repository\PoiCollectionRepository;
use TYPO3\CMS\Backend\Form\Element\AbstractFormElement;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\PathUtility;
use TYPO3\CMS\Extbase\Object\ObjectManager;
use TYPO3\CMS\Extbase\Reflection\ObjectAccess;
use TYPO3\CMS\Extbase\Security\Cryptography\HashService;
use TYPO3\CMS\Fluid\View\StandaloneView;

/**
 * Special backend FormEngine element to show Google Maps
 */
class GoogleMapsElement extends AbstractFormElement
{
    /**
     * @var \TYPO3\CMS\Extbase\Object\ObjectManager
     */
    protected $objectManager;

    /**
     * @var \JWeiland\Maps2\Configuration\ExtConf
     */
    protected $extConf;

    /**
     * @var \TYPO3\CMS\Extbase\Security\Cryptography\HashService
     */
    protected $hashService;

    /**
     * @var \TYPO3\CMS\Fluid\View\StandaloneView
     */
    protected $view;

    /**
     * @var \TYPO3\CMS\Core\Page\PageRenderer
     */
    protected $pageRenderer;

    /**
     * @var \JWeiland\Maps2\Domain\Repository\PoiCollectionRepository
     */
    protected $poiCollectionRepository;

    /**
     * initializes this class
     */
    public function init()
    {
        $this->objectManager = GeneralUtility::makeInstance(ObjectManager::class);
        $this->extConf = $this->objectManager->get(ExtConf::class);
        $this->hashService = $this->objectManager->get(HashService::class);
        $this->view = $this->objectManager->get(StandaloneView::class);
        $this->pageRenderer = $this->objectManager->get(PageRenderer::class);
        $this->poiCollectionRepository = $this->objectManager->get(PoiCollectionRepository::class);
    }

    /**
     * This will render Google Maps within PoiCollection records with a marker you can drag and drop
     *
     * @return array As defined in initializeResultArray() of AbstractNode
     * @throws \Exception
     */
    public function render()
    {
        $this->init();
        $currentRecord = $this->cleanUpCurrentRecord($this->data['databaseRow']);

        // loadRequireJsModule has to be loaded before configuring additional paths, else all ext paths will not be initialized
        $this->pageRenderer->addRequireJsConfiguration([
            'paths' => [
                'async' => rtrim(
                    PathUtility::getRelativePath(
                        PATH_typo3,
                        GeneralUtility::getFileAbsFileName('EXT:maps2/Resources/Public/JavaScript/async')
                    ),
                    '/'
                )
            ]
        ]);
        // make Google Maps2 available as dependency for all RequireJS modules
        $this->pageRenderer->addJsInlineCode(
            'definegooglemaps',
            sprintf(
                '// convert Google Maps into an AMD module
                define("gmaps", ["async!%s"],
                function() {
                    // return the gmaps namespace for brevity
                    return window.google.maps;
                });',
                $this->extConf->getGoogleMapsLibrary()
            ),
            false
        );
        $resultArray['stylesheetFiles'][] = 'EXT:maps2/Resources/Public/Css/GoogleMapsModule.css';
        $resultArray['requireJsModules'][] = [
            'TYPO3/CMS/Maps2/GoogleMapsModule' => 'function(GoogleMaps){GoogleMaps();}'
        ];

        $resultArray['html'] = $this->getMapHtml($this->getConfiguration($currentRecord));

        return $resultArray;
    }

    /**
     * Since TYPO3 7.5 $this->data['databaseRow'] consists of arrays where TCA was configured as type "select"
     * Convert these types back to strings/int
     *
     * @param array $currentRecord
     * @return array
     */
    protected function cleanUpCurrentRecord(array $currentRecord)
    {
        foreach ($currentRecord as $field => $value) {
            $currentRecord[$field] = is_array($value) ? $value[0] : $value;
        }
        return $currentRecord;
    }

    /**
     * Get configuration array from PA array
     *
     * @param array $currentRecord
     * @return array
     * @throws \Exception
     */
    protected function getConfiguration(array $currentRecord)
    {
        $config = [];

        // get poi collection model
        $uid = (int)$currentRecord['uid'];
        $poiCollection = $this->poiCollectionRepository->findByUid($uid);
        if ($poiCollection instanceof PoiCollection) {
            // set map center
            $config['latitude'] = $poiCollection->getLatitude();
            $config['longitude'] = $poiCollection->getLongitude();
            switch ($poiCollection->getCollectionType()) {
                case 'Route':
                case 'Area':
                    // set pois
                    /** @var $poi \JWeiland\Maps2\Domain\Model\Poi */
                    foreach ($poiCollection->getPois() as $poi) {
                        $latLng['latitude'] = $poi->getLatitude();
                        $latLng['longitude'] = $poi->getLongitude();
                        $config['pois'][] = $latLng;
                    }
                    if (!isset($config['pois'])) {
                        $config['pois'] = [];
                    }
                    break;
                case 'Radius':
                    $config['radius'] = ($poiCollection->getRadius()) ? $poiCollection->getRadius() : $this->extConf->getDefaultRadius();
                    $config['radius'] = $poiCollection->getRadius();
                    break;
                default:
                    break;
            }

            $config['address'] =  $currentRecord['address'];
            $config['collectionType'] = is_array($currentRecord['collection_type']) ? $currentRecord['collection_type'][0] : $currentRecord['collection_type'];
            $config['uid'] =  $uid;

            $hashArray['uid'] = $uid;
            $hashArray['collectionType'] = $currentRecord['collection_type'];
            $config['hash'] = $this->hashService->generateHmac(serialize($hashArray));
        }
        return $config;
    }

    /**
     * get parsed content from template
     *
     * @param array $config
     *
     * @return string
     */
    protected function getMapHtml(array $config)
    {
        $extPath = ExtensionManagementUtility::extPath('maps2');
        $this->view->setTemplatePathAndFilename($extPath . 'Resources/Private/Templates/Tca/GoogleMaps.html');
        $this->view->assign('config', json_encode($config));
        $this->view->assign('extConf', json_encode(ObjectAccess::getGettableProperties($this->extConf)));

        return $this->view->render();
    }
}
