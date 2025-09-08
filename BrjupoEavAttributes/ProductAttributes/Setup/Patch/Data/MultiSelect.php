<?php

namespace BrjupoEavAttributes\ProductAttributes\Setup\Patch\Data;

use Magento\Eav\Api\AttributeRepositoryInterface;
use BrjupoEavAttributes\ProductAttributes\Model\CreateProductEavAttribute;
use Psr\Log\LoggerInterface;

/**
 * Adobe Commerce Docs - Default dependencies for Data Patch
 * https://developer.adobe.com/commerce/php/development/components/declarative-schema/patches/
 */

use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Framework\Setup\Patch\PatchRevertableInterface;

class MultiSelect implements DataPatchInterface, PatchRevertableInterface
{
    const PRODUCT_ATTRIBUTE_CODE = 'Test_2';

    const MULTISELECT_TYPE = 'multiselect';

    protected AttributeRepositoryInterface $attributeRepository;
    protected CreateProductEavAttribute $createProductEavAttribute;

    protected LoggerInterface $logger;

    /**
     * @var ModuleDataSetupInterface
     */
    private $moduleDataSetup;


    public function __construct(
        AttributeRepositoryInterface $attributeRepository,
        CreateProductEavAttribute    $createProductEavAttribute,
        LoggerInterface              $logger,
        ModuleDataSetupInterface     $moduleDataSetup
    )
    {
        $this->attributeRepository = $attributeRepository;
        $this->createProductEavAttribute = $createProductEavAttribute;
        $this->logger = $logger;
        $this->moduleDataSetup = $moduleDataSetup;
    }

    public static function getDependencies()
    {
        return [];
    }

    public function getAliases()
    {
        return [];
    }

    public function apply()
    {
        $this->moduleDataSetup->getConnection()->startSetup();
        $this->logger->debug('INICIANDO +++++++++++++++++++++++++++++++++++++++++++++++++ ');
        $multiselectProductAttributeData = [];

        $multiselectProductAttributeData['frontend_label'][0] = 'Nombre a mostrar en el Admin y Frontend';
        $multiselectProductAttributeData['frontend_input'] = self::MULTISELECT_TYPE;
        $multiselectProductAttributeData['is_required'] = false;
        $multiselectProductAttributeData['attribute_code'] = self::PRODUCT_ATTRIBUTE_CODE;

        $multiselectProductAttributeData['is_global'] = '2'; //0 - Store View, 2 - Website, 1 - Global
        $multiselectProductAttributeData['is_unique'] = false;
        $multiselectProductAttributeData['is_used_in_grid'] = false;
        $multiselectProductAttributeData['is_visible_in_grid'] = true;
        $multiselectProductAttributeData['is_filterable_in_grid'] = false;
        $multiselectProductAttributeData['is_searchable'] = true;
        $multiselectProductAttributeData['search_weight'] = '1';
        $multiselectProductAttributeData['is_visible_in_advanced_search'] = false;
        $multiselectProductAttributeData['is_comparable'] = false;
        $multiselectProductAttributeData['is_filterable'] = true;   // Needed for Search Criteria Buckets. // Filterable with results
        $multiselectProductAttributeData['is_filterable_in_search'] = false;

        $multiselectProductAttributeData['is_used_for_promo_rules'] = false;
        $multiselectProductAttributeData['is_html_allowed_on_front'] = true;
        $multiselectProductAttributeData['is_visible_on_front'] = false;
        $multiselectProductAttributeData['used_in_product_listing'] = false;
        //$multiselectProductAttributeData[''] = ;

        /**
         *
         * update_product_preview_image=0&
         * use_product_image_for_swatch=0&
         *
         * default_value_text=&
         * default_value_yesno=0&
         */

        $this->createProductEavAttribute->execute($multiselectProductAttributeData);

        $this->logger->debug('FIN +++++++++++++++++++++++++++++++++++++++++++++++++ ');
        $this->moduleDataSetup->getConnection()->endSetup();
    }

    public function revert()
    {
        $this->moduleDataSetup->getConnection()->startSetup();
        //Here should go code that will revert all operations from `apply` method

        $entityTypeCode = \Magento\Catalog\Model\Product::ENTITY;
        $attributeCode = self::PRODUCT_ATTRIBUTE_CODE;
        $attributeData = $this->attributeRepository->get($entityTypeCode, $attributeCode);
        $this->attributeRepository->delete($attributeData);

        $this->moduleDataSetup->getConnection()->endSetup();
    }
}
