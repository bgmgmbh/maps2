<?php
namespace JWeiland\Maps2\Form\Resolver;

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
use JWeiland\Maps2\Form\Element\InfoWindowCkEditorElement;
use JWeiland\Maps2\Form\Element\InfoWindowRteHtmlareaElement;
use TYPO3\CMS\Backend\Form\Element\TextElement;
use TYPO3\CMS\Backend\Form\NodeFactory;
use TYPO3\CMS\Backend\Form\NodeResolverInterface;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;

/**
 * This resolver will return the RichTextElement render class of ext:rtehtmlarea or EXT:ckeditor if RTE is enabled
 * for this field.
 */
class InfoWindowContentResolver implements NodeResolverInterface
{
    /**
     * Global options from NodeFactory
     *
     * @var array
     */
    protected $data = [];

    /**
     * Default constructor receives full data array
     *
     * @param NodeFactory $nodeFactory
     * @param array $data
     */
    public function __construct(NodeFactory $nodeFactory, array $data)
    {
        $this->data = $data;
    }

    /**
     * Returns RichTextElement as class name if RTE widget should be rendered.
     *
     * @return string|null New class name or void if this resolver does not change current class name.
     */
    public function resolve()
    {
        if (version_compare(TYPO3_branch, '7.6') <= 0) {
            $table = $this->data['tableName'];
            $fieldName = $this->data['fieldName'];
            $row = $this->data['databaseRow'];
            $parameterArray = $this->data['parameterArray'];
            $backendUser = $this->getBackendUserAuthentication();

            if (// This field is not read only
                !$parameterArray['fieldConf']['config']['readOnly']
                // If RTE is generally enabled by user settings and RTE object registry can return something valid
                && $backendUser->isRTE()
            ) {
                // @todo: Most of this stuff is prepared by data providers within $this->data already
                $specialConfiguration = BackendUtility::getSpecConfParts($parameterArray['fieldConf']['defaultExtras']);
                // If "richtext" is within defaultExtras
                if (isset($specialConfiguration['richtext'])) {
                    // Operates by reference on $row! 'pid' is changed ...
                    BackendUtility::fixVersioningPid($table, $row);
                    list($recordPid, $tsConfigPid) = BackendUtility::getTSCpidCached($table, $row['uid'], $row['pid']);
                    // If the pid-value is not negative (that is, a pid could NOT be fetched)
                    if ($tsConfigPid >= 0) {
                        // Fetch page ts config and do some magic with it to find out if RTE is disabled on TS level.
                        $rteSetup = $backendUser->getTSConfig('RTE', BackendUtility::getPagesTSconfig($recordPid));
                        $rteTcaTypeValue = $this->data['recordTypeValue'];
                        $rteSetupConfiguration = BackendUtility::RTEsetup($rteSetup['properties'], $table, $fieldName, $rteTcaTypeValue);
                        if (!$rteSetupConfiguration['disabled']) {
                            // Finally, we're sure the editor should really be rendered ...
                            return InfoWindowRteHtmlareaElement::class;
                        }
                    }
                }
            }
        } else {
            $parameterArray = $this->data['parameterArray'];
            $backendUser = $this->getBackendUserAuthentication();
            if (// This field is not read only
                !$parameterArray['fieldConf']['config']['readOnly']
                // If RTE is generally enabled by user settings and RTE object registry can return something valid
                && $backendUser->isRTE()
                // If RTE is enabled for field
                && isset($parameterArray['fieldConf']['config']['enableRichtext'])
                && (bool)$parameterArray['fieldConf']['config']['enableRichtext'] === true
                // If RTE config is found (prepared by TcaText data provider)
                && isset($parameterArray['fieldConf']['config']['richtextConfiguration'])
                && is_array($parameterArray['fieldConf']['config']['richtextConfiguration'])
                // If RTE is not disabled on configuration level
                && !$parameterArray['fieldConf']['config']['richtextConfiguration']['disabled']
            ) {
                return InfoWindowCkEditorElement::class;
            }
        }

        return TextElement::class;
    }

    /**
     * @return BackendUserAuthentication
     */
    protected function getBackendUserAuthentication()
    {
        return $GLOBALS['BE_USER'];
    }
}
