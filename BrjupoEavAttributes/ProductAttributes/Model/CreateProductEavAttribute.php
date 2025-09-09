<?php

namespace BrjupoEavAttributes\ProductAttributes\Model;


use Laminas\Validator\Regex;
use Magento\Backend\App\Action\Context;
use Magento\Backend\Model\View\Result\Redirect;
use Magento\Catalog\Api\Data\ProductAttributeInterface;
use Magento\Catalog\Helper\Product;
use Magento\Catalog\Model\Product\Attribute\Frontend\Inputtype\Presentation;
use Magento\Framework\Serialize\Serializer\FormData;
use Magento\Catalog\Model\Product\AttributeSet\BuildFactory;
use Magento\Catalog\Model\ResourceModel\Eav\AttributeFactory;
use Magento\Eav\Model\Adminhtml\System\Config\Source\Inputtype\Validator;
use Magento\Eav\Model\Adminhtml\System\Config\Source\Inputtype\ValidatorFactory;
use Magento\Eav\Model\Entity\Attribute\Set;
use Magento\Eav\Model\ResourceModel\Entity\Attribute\Group\CollectionFactory;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\Cache\FrontendInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Filter\FilterManager;
use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\Registry;
use Magento\Framework\View\LayoutFactory;
use Magento\Framework\View\Result\PageFactory;

/**
 * This class will work as main functionality to create Custom Product Attributes programmatically
 * Taking native class as example, this class is found when we save an attribute manually in Admin
 * This "execute" method, now will return a void or exception INSTEAD of redirect as the native class does
 * Also will receive $data as params INSTEAD of a request->getPostValue()
 *
 * Code copied from
 * module-catalog/Controller/Adminhtml/Product/Attribute/Save.php
 */
class CreateProductEavAttribute
{
    /**
     * @var BuildFactory
     */
    protected $buildFactory;

    /**
     * @var FilterManager
     */
    protected $filterManager;

    /**
     * @var Product
     */
    protected $productHelper;

    /**
     * @var AttributeFactory
     */
    protected $attributeFactory;

    /**
     * @var ValidatorFactory
     */
    protected $validatorFactory;

    /**
     * @var CollectionFactory
     */
    protected $groupCollectionFactory;

    /** @var ObjectManagerInterface */

    protected $_objectManager;

    /**
     * @var LayoutFactory
     */
    private $layoutFactory;

    /**
     * @var Presentation
     */
    private $presentation;

    /**
     * @var FormData|null
     */
    private $formDataSerializer;

    /**
     * @var string
     */
    protected $_entityTypeId;


    public function __construct(
        //Context           $context,
        ObjectManagerInterface $_objectManager,
        //Registry          $coreRegistry,
        //PageFactory       $resultPageFactory,
        BuildFactory      $buildFactory,
        AttributeFactory  $attributeFactory,
        ValidatorFactory  $validatorFactory,
        CollectionFactory $groupCollectionFactory,
        FilterManager     $filterManager,
        Product           $productHelper,
        LayoutFactory     $layoutFactory,
        ?Presentation     $presentation = null,
        ?FormData         $formDataSerializer = null
    )
    {
        //parent::__construct($context, $attributeLabelCache, $coreRegistry, $resultPageFactory);
        $this->_objectManager = $_objectManager;
        $this->buildFactory = $buildFactory;
        $this->filterManager = $filterManager;
        $this->productHelper = $productHelper;
        $this->attributeFactory = $attributeFactory;
        $this->validatorFactory = $validatorFactory;
        $this->groupCollectionFactory = $groupCollectionFactory;
        $this->layoutFactory = $layoutFactory;
        $this->presentation = $presentation ?: ObjectManager::getInstance()->get(Presentation::class);
        $this->formDataSerializer = $formDataSerializer
            ?: ObjectManager::getInstance()->get(FormData::class);

        $this->_entityTypeId = $this->_objectManager->create(
            \Magento\Eav\Model\Entity::class
        )->setType(
            \Magento\Catalog\Model\Product::ENTITY
        )->getTypeId();
    }

    /**
     * @inheritdoc
     *
     * @return Redirect
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     */
    public function execute($data)
    {
        if (!$data) {
            throw new LocalizedException(__('Data is NOT provided'));
            return;
        }

        // In the data patch BrjupoEavAttributes/CustomerAddress/Setup/Patch/Data/AttributeDropdown.php
        // The serialized_options has been unsearealized for better human reading
        // If you want to understand the Post data unserialized, check vendor file
        // module-catalog/Controller/Adminhtml/Product/Attribute/Save.php
        try {
            if (isset($data['serialized_options'])) {
                $optionData = $data['serialized_options'];
            } else {
                $optionData = [];
            }
        } catch (\InvalidArgumentException $e) {
            throw new LocalizedException(__("The attribute couldn't be saved due to an error. Verify your information and try again. "
                . "If the error persists, please try again later. " . $e->getMessage()));
            return;
        }

        $data = array_replace_recursive(
            $data,
            $optionData
        );

        $attributeId = null;
        if (is_array($data) && isset($data['attribute_id'])) {
            $attributeId = $data['attribute_id'];
        }

        /** @var ProductAttributeInterface $model */
        $model = $this->attributeFactory->create();
        if ($attributeId) {
            $model->load($attributeId);
        }
        $attributeCode = $model && $model->getId()
            ? $model->getAttributeCode()
            : $data['attribute_code'];
        if (!$attributeCode) {
            $frontendLabel = $data['frontend_label'][0] ?? '';
            $attributeCode = $this->generateCode($frontendLabel);
        }
        $data['attribute_code'] = $attributeCode;

        //validate frontend_input
        if (isset($data['frontend_input'])) {
            /** @var Validator $inputType */
            $inputType = $this->validatorFactory->create();
            if (!$inputType->isValid($data['frontend_input'])) {
                $fullErrorMessage = '';
                foreach ($inputType->getMessages() as $message) {
                    $fullErrorMessage .= ' ' . $message;
                }
                throw new LocalizedException(__($fullErrorMessage));
                return;
            }
        }

        $data = $this->presentation->convertPresentationDataToInputType($data);


        if ($attributeId) {
            if (!$model->getId()) {
                throw new LocalizedException(__('This attribute no longer exists.'));
                return;
            }
            // entity type check
            if ($model->getEntityTypeId() != $this->_entityTypeId || array_key_exists('backend_model', $data)) {
                throw new LocalizedException(__('We can\'t update the attribute.'));
                return;
            }

            $data['attribute_code'] = $model->getAttributeCode();
            $data['is_user_defined'] = $model->getIsUserDefined();
            $data['frontend_input'] = $data['frontend_input'] ?? $model->getFrontendInput();
        } else {
            /**
             * @todo add to helper and specify all relations for properties
             */
            $data['source_model'] = $this->productHelper->getAttributeSourceModelByInputType(
                $data['frontend_input']
            );
            $data['backend_model'] = $this->productHelper->getAttributeBackendModelByInputType(
                $data['frontend_input']
            );

            if ($model->getIsUserDefined() === null) {
                $data['backend_type'] = $model->getBackendTypeByInput($data['frontend_input']);
            }
        }


        $data += ['is_filterable' => 0, 'is_filterable_in_search' => 0];

        $defaultValueField = $model->getDefaultValueByInput($data['frontend_input']);
        if ($defaultValueField) {
            $data['default_value'] = $data[$defaultValueField];
        }

        if (!$model->getIsUserDefined() && $model->getId()) {
            // Unset attribute field for system attributes
            unset($data['apply_to']);
        }

        if ($model->getBackendType() == 'static' && !$model->getIsUserDefined()) {
            $data['frontend_class'] = $model->getFrontendClass();
        }

        unset($data['entity_type_id']);

        if (array_key_exists('reset_is-default_option', $data) && $data['reset_is-default_option']) {
            unset($data['reset_is-default_option']);
            $data['default_value'] = null;
        }

        $model->addData($data);

        if (!$attributeId) {
            $model->setEntityTypeId($this->_entityTypeId);
            $model->setIsUserDefined(1);
        }

        try {
            $model->save();
            return;
        } catch (\Exception $e) {
            throw new LocalizedException(__($e->getMessage()));
            return;
        }

        return;
    }

    /**
     * Generate code from label
     *
     * @param string $label
     * @return string
     */
    protected function generateCode($label)
    {
        $code = substr(
            preg_replace(
                '/[^a-z_0-9]/',
                '_',
                $this->_objectManager->create(\Magento\Catalog\Model\Product\Url::class)->formatUrlKey($label)
            ),
            0,
            30
        );
        $validatorAttrCode = new Regex(['pattern' => '/^[a-z][a-z_0-9]{0,29}[a-z0-9]$/']);
        if (!$validatorAttrCode->isValid($code)) {
            // md5() here is not for cryptographic use.
            // phpcs:ignore Magento2.Security.InsecureFunction
            $code = 'attr_' . ($code ?: substr(md5(time()), 0, 8));
        }
        return $code;
    }
}
