<?php

namespace Pim\Bundle\MagentoConnectorBundle\Processor;

use Pim\Bundle\CatalogBundle\Manager\ChannelManager;
use Oro\Bundle\BatchBundle\Item\InvalidItemException;

use Pim\Bundle\MagentoConnectorBundle\Validator\Constraints\HasValidCredentials;
use Pim\Bundle\MagentoConnectorBundle\Webservice\MagentoWebservice;
use Pim\Bundle\MagentoConnectorBundle\Manager\PriceMappingManager;
use Pim\Bundle\MagentoConnectorBundle\Guesser\MagentoWebserviceGuesser;
use Pim\Bundle\MagentoConnectorBundle\Guesser\MagentoNormalizerGuesser;
use Pim\Bundle\MagentoConnectorBundle\Manager\GroupManager;
use Pim\Bundle\MagentoConnectorBundle\Normalizer\Exception\NormalizeException;

/**
 * Magento configurable processor
 *
 * @author    Julien Sanchez <julien@akeneo.com>
 * @copyright 2013 Akeneo SAS (http://www.akeneo.com)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 *
 * @HasValidCredentials()
 */
class ConfigurableMagentoProcessor extends AbstractProductMagentoProcessor
{
    /**
     * @var ConfigurableNormalizer
     */
    protected $configurableNormalizer;

    /**
     * @var GroupManager
     */
    protected $groupManager;

    /**
     * @param ChannelManager           $channelManager
     * @param MagentoWebserviceGuesser $magentoWebserviceGuesser
     * @param ProductNormalizerGuesser $magentoNormalizerGuesser
     * @param GroupManager             $groupManager
     */
    public function __construct(
        ChannelManager $channelManager,
        MagentoWebserviceGuesser $magentoWebserviceGuesser,
        MagentoNormalizerGuesser $magentoNormalizerGuesser,
        GroupManager $groupManager
    ) {
        parent::__construct($channelManager, $magentoWebserviceGuesser, $magentoNormalizerGuesser);

        $this->groupManager = $groupManager;
    }

    /**
     * Function called before all process
     */
    protected function beforeProcess()
    {
        parent::beforeProcess();

        $priceMappingManager          = new PriceMappingManager($this->defaultLocale, $this->currency);
        $this->configurableNormalizer = $this->magentoNormalizerGuesser->getConfigurableNormalizer(
            $this->getClientParameters(),
            $this->productNormalizer,
            $priceMappingManager
        );
    }

    /**
     * {@inheritdoc}
     */
    public function process($items)
    {
        $processedItems = array();

        $this->beforeProcess();

        $groupsIds            = $this->getGroupRepository()->getVariantGroupIds();
        $configurables        = $this->getProductsForGroups($items, $groupsIds);
        $magentoConfigurables = $this->magentoWebservice->getConfigurablesStatus($configurables);

        foreach ($configurables as $configurable) {
            if (count($configurable['products']) == 0) {
                throw new InvalidItemException(
                    'The variant group is not associated to any products',
                    array($configurable)
                );
            }

            if ($this->magentoConfigurableExist($configurable, $magentoConfigurables)) {
                $context = array_merge(
                    $this->globalContext,
                    array('attributeSetId' => 0, 'create' => false)
                );
            } else {
                $groupFamily = $this->getGroupFamily($configurable);
                $context     = array_merge(
                    $this->globalContext,
                    array(
                        'attributeSetId' => $this->getAttributeSetId($groupFamily->getCode(), $configurable),
                        'create'         => true
                    )
                );
            }

            $processedItems[] = $this->normalizeConfigurable($configurable, $context);
        }

        return $processedItems;
    }

    /**
     * Normalize the given configurable
     *
     * @param array $configurable The given configurable
     * @param array $context      The context
     *
     * @throws InvalidItemException If a normalization error occured
     * @return array                processed item
     */
    protected function normalizeConfigurable($configurable, $context)
    {
        try {
            $processedItem = $this->configurableNormalizer->normalize($configurable, 'MagentoArray', $context);
        } catch (NormalizeException $e) {
            throw new InvalidItemException($e->getMessage(), array($configurable['group']));
        }

        return $processedItem;
    }

    /**
     * Test if a configurable allready exist on magento platform
     *
     * @param array $configurable         The configurable
     * @param array $magentoConfigurables Magento configurables
     *
     * @return bool
     */
    protected function magentoConfigurableExist($configurable, $magentoConfigurables)
    {
        foreach ($magentoConfigurables as $magentoConfigurable) {

            if ($magentoConfigurable['sku'] == sprintf(
                MagentoWebservice::CONFIGURABLE_IDENTIFIER_PATTERN,
                $configurable['group']->getCode()
            )) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get the family of the given configurable
     * @param array $configurable
     *
     * @throws InvalidItemException If there are two products with different families
     * @return Family
     */
    protected function getGroupFamily($configurable)
    {
        $groupFamily = $configurable['products'][0]->getFamily();

        foreach ($configurable['products'] as $product) {
            if ($groupFamily != $product->getFamily()) {
                throw new InvalidItemException(
                    'Your variant group contains products from different families. Magento cannot handle ' .
                    'configurable products with heterogen attribute sets'
                );
            }
        }

        return $groupFamily;
    }

    /**
     * Get products association for each groups
     * @param array $products
     * @param array $groupsIds
     *
     * @return array
     */
    protected function getProductsForGroups(array $products, array $groupsIds)
    {
        $groups = array();

        foreach ($products as $product) {
            foreach ($product->getGroups() as $group) {
                $groupId = $group->getId();

                if (in_array($groupId, $groupsIds)) {
                    if (!isset($groups[$groupId])) {
                        $groups[$groupId] = array(
                            'group'    => $group,
                            'products' => array()
                        );
                    }

                    $groups[$groupId]['products'][] = $product;
                }
            }
        }

        return $groups;
    }

    /**
     * Get the group repository
     *
     * @return \Doctrine\ORM\EntityRepository
     */
    protected function getGroupRepository()
    {
        return $this->groupManager->getRepository();
    }
}
