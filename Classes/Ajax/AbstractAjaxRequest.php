<?php
namespace JWeiland\Maps2\Ajax;

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

use JWeiland\Maps2\Domain\Model\PoiCollection;
use TYPO3\CMS\Extbase\Configuration\BackendConfigurationManager;
use TYPO3\CMS\Extbase\Object\ObjectManager;
use TYPO3\CMS\Extbase\Persistence\PersistenceManagerInterface;
use TYPO3\CMS\Extbase\Reflection\PropertyReflection;
use TYPO3\CMS\Extbase\Security\Cryptography\HashService;

/**
 * An abstract class for ajax requests
 */
abstract class AbstractAjaxRequest implements AjaxInterface
{
    /**
     * @var ObjectManager
     */
    protected $objectManager;

    /**
     * @var PersistenceManagerInterface
     */
    protected $persistenceManager;

    /**
     * @var HashService
     */
    protected $hashService;

    /**
     * @var BackendConfigurationManager
     */
    protected $backendConfigurationManager;

    /**
     * inject objectManager
     *
     * @param ObjectManager $objectManager
     * @return void
     */
    public function injectObjectManager(ObjectManager $objectManager)
    {
        $this->objectManager = $objectManager;
    }

    /**
     * inject persistenceManager
     *
     * @param PersistenceManagerInterface $persistenceManager
     * @return void
     */
    public function injectPersistenceManager(PersistenceManagerInterface $persistenceManager)
    {
        $this->persistenceManager = $persistenceManager;
    }

    /**
     * inject hashService
     *
     * @param HashService $hashService
     * @return void
     */
    public function injectHashService(HashService $hashService)
    {
        $this->hashService = $hashService;
    }

    /**
     * inject backendConfigurationManager
     *
     * @param BackendConfigurationManager $backendConfigurationManager
     * @return void
     */
    public function injectBackendConfigurationManager(BackendConfigurationManager $backendConfigurationManager)
    {
        $this->backendConfigurationManager = $backendConfigurationManager;
    }

    /**
     * In Typo3QuerySettings there is a feature check which loads whole TS which needs about 250ms
     * With this workaround I modify the 1st level cache of configuration manager
     *
     * @return void
     */
    public function initializeObject()
    {
        // set minimal configuration
        $configuration = [];
        $configuration['_']['features']['ignoreAllEnableFieldsInBe'] = 0;

        // transport our minimal configuration into backendConfigurationManagers 1st-level Cache
        if (property_exists(get_class($this->backendConfigurationManager), 'configurationCache')) {
            $propertyReflection = new PropertyReflection(
                get_class($this->backendConfigurationManager),
                'configurationCache'
            );
            $propertyReflection->setAccessible(true);
            $propertyReflection->setValue($this->backendConfigurationManager, $configuration);
        }
    }
    /**
     * validate arguments against hash
     *
     * @param PoiCollection $poiCollection Model to validate hash against
     * @param string $hash A generated hash value to verify that there are no modifications in the uri
     * @return bool
     */
    public function validateArguments(PoiCollection $poiCollection, $hash)
    {
        $hashArray['uid'] = $poiCollection->getUid();
        $hashArray['collectionType'] = $poiCollection->getCollectionType();
        return $this->hashService->validateHmac(serialize($hashArray), $hash);
    }
}
