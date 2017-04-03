<?php

namespace ElasticExportGoogleShopping\Generator;

use Plenty\Modules\DataExchange\Contracts\CSVPluginGenerator;
use Plenty\Modules\Helper\Services\ArrayHelper;
use Plenty\Modules\Item\DataLayer\Models\Record;
use Plenty\Modules\Item\DataLayer\Models\RecordList;
use Plenty\Modules\DataExchange\Models\FormatSetting;
use ElasticExport\Helper\ElasticExportCoreHelper;
use Plenty\Modules\Helper\Models\KeyValue;
use Plenty\Modules\Item\Attribute\Contracts\AttributeRepositoryContract;
use Plenty\Modules\Item\Attribute\Models\Attribute;
use Plenty\Modules\Item\Attribute\Contracts\AttributeValueNameRepositoryContract;
use Plenty\Modules\Item\Attribute\Models\AttributeValueName;
use Plenty\Modules\Item\Attribute\Contracts\AttributeValueRepositoryContract;
use Plenty\Modules\Item\Attribute\Models\AttributeValue;
use Plenty\Modules\Item\Property\Contracts\PropertySelectionRepositoryContract;
use Plenty\Modules\Item\Property\Models\PropertySelection;
use Plenty\Repositories\KeyValueStorageRepository;
use Plenty\Repositories\Models\PaginatedResult;

class GoogleShopping extends CSVPluginGenerator
{
    const CHARACTER_TYPE_GENDER						= 'gender';
    const CHARACTER_TYPE_AGE_GROUP					= 'age_group';
    const CHARACTER_TYPE_SIZE_TYPE					= 'size_type';
    const CHARACTER_TYPE_SIZE_SYSTEM				= 'size_system';
    const CHARACTER_TYPE_ENERGY_EFFICIENCY_CLASS	= 'energy_efficiency_class';
    const CHARACTER_TYPE_EXCLUDED_DESTINATION		= 'excluded_destination';
    const CHARACTER_TYPE_ADWORDS_REDIRECT			= 'adwords_redirect';
    const CHARACTER_TYPE_MOBILE_LINK				= 'mobile_link';
    const CHARACTER_TYPE_SALE_PRICE_EFFECTIVE_DATE	= 'sale_price_effective_date';
    const CHARACTER_TYPE_CUSTOM_LABEL_0				= 'custom_label_0';
    const CHARACTER_TYPE_CUSTOM_LABEL_1				= 'custom_label_1';
    const CHARACTER_TYPE_CUSTOM_LABEL_2				= 'custom_label_2';
    const CHARACTER_TYPE_CUSTOM_LABEL_3				= 'custom_label_3';
    const CHARACTER_TYPE_CUSTOM_LABEL_4				= 'custom_label_4';
    const CHARACTER_TYPE_DESCRIPTION				= 'description';
    const CHARACTER_TYPE_COLOR						= 'color';
    const CHARACTER_TYPE_SIZE						= 'size';
    const CHARACTER_TYPE_PATTERN					= 'pattern';
    const CHARACTER_TYPE_MATERIAL					= 'material';

    const ISO_CODE_2								= 'isoCode2';
    const ISO_CODE_3								= 'isoCode3';

    const GOOGLE_SHOPPING                           = 7.00;

    /**
     * @var ElasticExportCoreHelper $elasticExportHelper
     */
    private $elasticExportHelper;

    /*
     * @var ArrayHelper
     */
    private $arrayHelper;

    /**
     * AttributeValueNameRepositoryContract $attributeValueNameRepository
     */
    private $attributeValueNameRepository;

    /**
     * AttributeRepositoryContract $attributeRepository
     */
    private $attributeRepository;

    /**
     * AttributeValueRepositoryContract $attributeValueRepository
     */
    private $attributeValueRepository;

    /**
     * PropertySelectionRepositoryContract $propertySelectionRepository
     */
    private $propertySelectionRepository;

    /**
     * @var array
     */
    private $itemPropertyCache = [];

    /**
     * @var array
     */
    private $attributeValueCache = [];

    /**
     * @var array
     */
    private $linkedAttributeList = [];

    /**
     * @var array $idlVariations
     */
    private $idlVariations = array();

    /**
     * GoogleShopping constructor.
     * @param ArrayHelper $arrayHelper
     * @param AttributeRepositoryContract $attributeRepository
     * @param AttributeValueRepositoryContract $attributeValueRepository
     * @param AttributeValueNameRepositoryContract $attributeValueNameRepository
     * @param PropertySelectionRepositoryContract $propertySelectionRepository
     */
    public function __construct(
        ArrayHelper $arrayHelper,
        AttributeValueNameRepositoryContract $attributeValueNameRepository,
        PropertySelectionRepositoryContract $propertySelectionRepository,
        AttributeValueRepositoryContract $attributeValueRepository,
        AttributeRepositoryContract $attributeRepository)
    {
        $this->arrayHelper = $arrayHelper;
        $this->attributeValueNameRepository = $attributeValueNameRepository;
        $this->propertySelectionRepository = $propertySelectionRepository;
        $this->attributeValueRepository = $attributeValueRepository;
        $this->attributeRepository = $attributeRepository;
    }

    /**
     * @param array $resultList
     * @param array $formatSettings
     * @param array $filter
     */
    protected function generatePluginContent($resultList, array $formatSettings = [], array $filter = [])
    {
        $this->elasticExportHelper = pluginApp(ElasticExportCoreHelper::class);
        if(!is_array($resultList) || count($resultList['documents']) <= 0)
        {
            return;
        }
        $settings = $this->arrayHelper->buildMapFromObjectList($formatSettings, 'key', 'value');
        $this->setDelimiter("	"); // this is tab character!

        $this->loadLinkedAttributeList($settings);

        $this->addCSVContent([
            'id',
            'title',
            'description',
            'google_product_category',
            'product_type',
            'link',
            'image_link',
            'condition',
            'availability',
            'price',
            'sale_price',
            'brand',
            'gtin',
            'isbn',
            'mpn',
            'color',
            'size',
            'material',
            'pattern',
            'item_group_id',
            'shipping',
            'shipping_weight',
            'gender',
            'age_group',
            'excluded_destination',
            'adwords_redirect',
            'identifier_exists',
            'unit_pricing_measure',
            'unit_pricing_base_measure',
            'energy_efficiency_class',
            'size_system',
            'size_type',
            'mobile_link',
            'sale_price_effective_date',
            'adult',
            'custom_label_0',
            'custom_label_1',
            'custom_label_2',
            'custom_label_3',
            'custom_label_4',
        ]);

        // create a List of all variation IDs
        $variationIdList = array();

        foreach($resultList['documents'] as $variation)
        {
            $variationIdList[] = $variation['id'];
        }

        // get the missing fields in ES from IDL
        $idlResultList = null;

        if(is_array($variationIdList) && count($variationIdList) > 0)
        {
            /**
             * @var \ElasticExportGoogleShopping\IDL_ResultList\GoogleShopping $idlResultList
             */
            $idlResultList = pluginApp(\ElasticExportGoogleShopping\IDL_ResultList\GoogleShopping::class);

            if($idlResultList instanceof \ElasticExportGoogleShopping\IDL_ResultList\GoogleShopping)
			{
				$idlResultList = $idlResultList->getResultList($variationIdList, $settings, $filter);
			}
        }

        // creates an array with the variationId as key to surpass the sorting problem
        if(isset($idlResultList) && $idlResultList instanceof RecordList)
        {
            $this->createIdlArray($idlResultList);
        }

        foreach($resultList['documents'] as $variation)
        {
            if(!array_key_exists($variation['id'], $this->idlVariations))
            {
                continue;
            }

            $variationAttributes = $this->getVariationAttributes($variation, $settings);
            $variationPrice = number_format((float)$this->idlVariations[$variation['id']]['variationRetailPrice.price'], 2, '.', '');
            $salePrice = number_format((float)$this->elasticExportHelper->getSpecialPrice($this->idlVariations[$variation['id']]['variationSpecialOfferRetailPrice.retailPrice'], $settings), 2, '.', '');

            if($salePrice >= $variationPrice || $salePrice <= 0.00)
            {
                $salePrice = '';
            }

            $shippingCost = $this->elasticExportHelper->getShippingCost($variation['data']['item']['id'], $settings);

            if(!is_null($shippingCost))
            {
                $shippingCost = number_format((float)$shippingCost, 2, '.', '');
            }
            else
            {
                $shippingCost = '';
            }

            if(strlen($shippingCost) == 0)
            {
                $shipping = '';
            }
            else
            {
                $shipping = $this->elasticExportHelper->getCountry($settings, self::ISO_CODE_2).':::'.$shippingCost;
            }

            $basePriceComponents = $this->getBasePriceComponents($variation, $settings);

            $imageList = $this->elasticExportHelper->getImageListInOrder($variation, $settings, 1, 'variationImages');

            $data = [
                'id' 						=> $this->elasticExportHelper->generateSku($variation['id'], self::GOOGLE_SHOPPING, 0, $variation['data']['skus']['sku']),
                'title' 					=> $this->elasticExportHelper->getName($variation, $settings, 256),
                'description'				=> $this->getDescription($variation, $settings),
                'google_product_category'	=> $this->elasticExportHelper->getCategoryMarketplace((int)$variation['data']['defaultCategories'][0]['id'], (int)$settings->get('plentyId'), 129),
                'product_type'				=> $this->elasticExportHelper->getCategory((int)$variation['data']['defaultCategories'][0]['id'], (string)$settings->get('lang'), (int)$settings->get('plentyId')),
                'link'						=> $this->elasticExportHelper->getUrl($variation, $settings, true, false),
                'image_link'				=> count($imageList) > 0 && array_key_exists(0, $imageList) ? $imageList[0] : '',
                'condition'					=> $this->getCondition($variation['data']['item']['conditionApi']),
                'availability'				=> $this->elasticExportHelper->getAvailability($variation, $settings, false),
                'price'						=> $variationPrice,
                'sale_price'				=> $salePrice,
                'brand'						=> $this->elasticExportHelper->getExternalManufacturerName((int)$variation['data']['item']['manufacturer']['id']),
                'gtin'						=> $this->elasticExportHelper->getBarcodeByType($variation, $settings->get('barcode')),
                'isbn'						=> $this->elasticExportHelper->getBarcodeByType($variation, ElasticExportCoreHelper::BARCODE_ISBN),
                'mpn'						=> $variation['data']['variation']['model'],
                'color'						=> $variationAttributes[self::CHARACTER_TYPE_COLOR],
                'size'						=> $variationAttributes[self::CHARACTER_TYPE_SIZE],
                'material'					=> $variationAttributes[self::CHARACTER_TYPE_MATERIAL],
                'pattern'					=> $variationAttributes[self::CHARACTER_TYPE_PATTERN],
                'item_group_id'				=> $variation['data']['item']['id'],
                'shipping'					=> $shipping,
                'shipping_weight'			=> $variation['data']['variation']['weightG'].' g',
                'gender'					=> $this->getProperty($variation, self::CHARACTER_TYPE_GENDER, $settings),
                'age_group'					=> $this->getProperty($variation, self::CHARACTER_TYPE_AGE_GROUP, $settings),
                'excluded_destination'		=> $this->getProperty($variation, self::CHARACTER_TYPE_EXCLUDED_DESTINATION, $settings),
                'adwords_redirect'			=> $this->getProperty($variation, self::CHARACTER_TYPE_ADWORDS_REDIRECT, $settings),
                'identifier_exists'			=> $this->getIdentifierExists($variation, $settings),
                'unit_pricing_measure'		=> $basePriceComponents['unit_pricing_measure'],
                'unit_pricing_base_measure'	=> $basePriceComponents['unit_pricing_base_measure'],
                'energy_efficiency_class'	=> $this->getProperty($variation, self::CHARACTER_TYPE_ENERGY_EFFICIENCY_CLASS, $settings),
                'size_system'				=> $this->getProperty($variation, self::CHARACTER_TYPE_SIZE_SYSTEM, $settings),
                'size_type'					=> $this->getProperty($variation, self::CHARACTER_TYPE_SIZE_TYPE, $settings),
                'mobile_link'				=> $this->getProperty($variation, self::CHARACTER_TYPE_MOBILE_LINK, $settings),
                'sale_price_effective_date'	=> $this->getProperty($variation, self::CHARACTER_TYPE_SALE_PRICE_EFFECTIVE_DATE, $settings),
                'adult'						=> '',
                'custom_label_0'			=> $this->getProperty($variation, self::CHARACTER_TYPE_CUSTOM_LABEL_0, $settings),
                'custom_label_1'			=> $this->getProperty($variation, self::CHARACTER_TYPE_CUSTOM_LABEL_1, $settings),
                'custom_label_2'			=> $this->getProperty($variation, self::CHARACTER_TYPE_CUSTOM_LABEL_2, $settings),
                'custom_label_3'			=> $this->getProperty($variation, self::CHARACTER_TYPE_CUSTOM_LABEL_3, $settings),
                'custom_label_4'			=> $this->getProperty($variation, self::CHARACTER_TYPE_CUSTOM_LABEL_4, $settings),
            ];

            $this->addCSVContent(array_values($data));
        }
    }

    /**
     * Get property.
     * @param array $variation
     * @param string $property
     * @param KeyValue $settings
     * @return string
     */
    private function getProperty($variation, string $propertyType, KeyValue $settings):string
    {
        $itemPropertyList = $this->getItemPropertyList($variation, $settings);

        switch ($propertyType)
        {
            case self::CHARACTER_TYPE_GENDER:
                $allowedList = [
                    'male',
                    'female',
                    'unisex',
                ];
                break;

            case self::CHARACTER_TYPE_AGE_GROUP:
                $allowedList = [
                    'newborn',
                    'infant',
                    'toddler',
                    'adult',
                    'kids',
                ];
                break;

            case self::CHARACTER_TYPE_SIZE_TYPE:
                $allowedList = [
                    'regular',
                    'petite',
                    'plus',
                    'maternity',
                ];
                break;

            case self::CHARACTER_TYPE_SIZE_SYSTEM:
                $allowedList = [
                    'US',
                    'UK',
                    'EU',
                    'DE',
                    'FR',
                    'JP',
                    'CN',
                    'IT',
                    'BR',
                    'MEX',
                    'AU',
                ];
                break;

            case self::CHARACTER_TYPE_ENERGY_EFFICIENCY_CLASS:
                $allowedList = [
                    'G',
                    'F',
                    'E',
                    'D',
                    'C',
                    'B',
                    'A',
                    'A+',
                    'A++',
                    'A+++',
                ];
                break;

            default:
                $allowedList = array();
        }

        if(array_key_exists($propertyType, $itemPropertyList) && (count($allowedList) <= 0 || in_array($itemPropertyList[$propertyType], $allowedList)))
        {
            return $itemPropertyList[$propertyType];
        }

        return '';
    }

    /**
     * @param array $variation
     * @param KeyValue $settings
     * @return array
     */
    private function getBasePriceComponents($variation, KeyValue $settings):array
    {
        $unitPricingMeasure = '';
        $unitPricingBaseMeasure = '';

        if ($variation['data']['unit']['id'] >= 1 && $variation['data']['unit']['content'] > 1)
        {
            if (in_array($variation['data']['unit']['id'], array('5','2','31','38')))
            {
                $unitPricingMeasure = ((string)number_format((float)$variation['data']['unit']['content'], 2, '.', '').' '.(string)$this->getUnit($variation['data']['unit']['id']));
            }
            elseif ($this->getUnit($variation['data']['unit']['id']) != '')
            {
                $unitPricingMeasure = ((string)number_format((float)$variation['data']['unit']['content'], 2, ',', '').' '.(string)$this->getUnit($variation['data']['unit']['id']));
            }

            if ($unitPricingMeasure != '')
            {
                $unitPricingBaseMeasure = $this->getUnitPricingBaseMeasure($variation, $settings);
            }
        }

        return array(
            'unit_pricing_measure'	  =>  $unitPricingMeasure,
            'unit_pricing_base_measure' =>  $unitPricingBaseMeasure
        );

    }

    /**
     * Get item properties.
     * @param array $variation
     * @param KeyValue $settings
     * @return array<string,string>
     */
    private function getItemPropertyList($variation, KeyValue $settings):array
    {
        if(!array_key_exists($variation['data']['item']['id'], $this->itemPropertyCache))
        {
            $characterMarketComponentList = $this->elasticExportHelper->getItemCharactersByComponent($this->idlVariations[$variation['data']['item']['id']], self::GOOGLE_SHOPPING);

            $list = [];

            if(count($characterMarketComponentList))
            {
                foreach($characterMarketComponentList as $data)
                {
                    if((string) $data['characterValueType'] != 'file' && (string) $data['characterValueType'] != 'empty' && (string) $data['externalComponent'] != "0")
                    {
                        if((string) $data['characterValueType'] == 'selection')
                        {
                            $propertySelection = $this->propertySelectionRepository->findOne((int) $data['characterItemId'], $settings->get('lang'));
                            foreach ($propertySelection as $selectionValue)
                            {
                                if( $selectionValue instanceof PropertySelection &&
                                    $selectionValue->id == $data['characterValue'] &&
                                    $selectionValue->lang == $settings->get('lang'))
                                {
                                    $list[(string) $data['externalComponent']] = (string) $selectionValue->name;
                                }
                            }
                        }
                        else
                        {
                            $list[(string) $data['externalComponent']] = (string) $data['characterValue'];
                        }

                    }
                }
            }

            $this->itemPropertyCache[$variation['data']['item']['id']] = $list;
        }

        return $this->itemPropertyCache[$variation['data']['item']['id']];
    }

    /**
     * Check if condition is valid.
     * @param int|null $condition
     * @return string
     */
    private function getCondition($conditionId):string
    {
        $conditionList = [
            0 => 'new',
            1 => 'used',
            2 => 'used',
            3 => 'used',
            4 => 'used',
            5 => 'refurbished'
        ];

        if (array_key_exists($conditionId, $conditionList))
        {
            return $conditionList[$conditionId];
        }
        else
        {
            return '';
        }
    }

    /**
     * Calculate and get unit price
     * @param array $variation
     * @return string
     */
    private function getIdentifierExists($variation, KeyValue $settings):string
    {
        $count = 0;
        if (strlen($variation['data']['variation']['model']) > 0)
        {
            $count++;
        }

        if (	strlen($this->elasticExportHelper->getBarcodeByType($variation, $settings->get('barcode'))) > 0 ||
            strlen($this->elasticExportHelper->getBarcodeByType($variation, ElasticExportCoreHelper::BARCODE_ISBN)) > 0 )
        {
            $count++;
        }

        if (strlen($this->elasticExportHelper->getExternalManufacturerName((int)$variation['data']['item']['manufacturer']['id'])) > 0)
        {
            $count++;
        }

        if ($count >= 2)
        {
            return 'true';
        }
        else
        {
            return 'false';
        }
    }

    /**
     * Returns the unit, if there is any unit configured, which is allowed
     * for GoogleShopping.
     *
     * @param  int $unitId
     * @return string
     */
    private function getUnit($unitId):string
    {
        switch((int)$unitId)
        {
            case '1':
                return 'ct'; //Stück
            case '32':
                return 'ml'; // Milliliter
            case '5':
                return 'l'; // Liter
            case '4':
                return 'mg'; //Milligramm
            case '3':
                return 'g'; // Gramm
            case '2':
                return 'kg'; // Kilogramm
            case '51':
                return 'cm'; // Zentimeter
            case '31':
                return 'm'; // Meter
            case '38':
                return 'sqm'; // Quadratmeter
            default:
                return '';
        }
    }

    /**
     * Calculate and get unit price
     * @param array $variation
     * @param KeyValue $settings
     * @return string
     */
    private function getUnitPricingBaseMeasure($variation, KeyValue $settings):string
    {
        $basePriceUnit = $this->getUnit($variation['data']['unit']['id']);

        if(in_array($variation['data']['unit']['id'], array('3','32')))
        {
            if($variation['data']['unit']['content'] <= 250)
            {
                $basePriceContent = 100;
            }
            else
            {
                $basePriceContent = 1;
                $basePriceUnit = $basePriceUnit=='g' ? 'kg' : 'l';
            }
        }
        else
        {
            $basePriceContent = 1;
        }

        return (string)$basePriceContent.' '.(string)$basePriceUnit;
    }

    /**
     * Get item description.
     * @param array $variation
     * @param KeyValue $settings
     * @return string
     */
    private function getDescription($variation, KeyValue $settings):string
    {
        $description = $this->elasticExportHelper->getItemCharacterByBackendName($variation, $settings, self::CHARACTER_TYPE_DESCRIPTION);

        if (strlen($description) <= 0)
        {
            $description = $this->elasticExportHelper->getDescription($variation, $settings, 5000);
        }

        return $description;
    }

    /**
     * Get variation attributes.
     * @param array $variation
     * @param KeyValue $settings
     * @return array<string,string>
     */
    private function getVariationAttributes($variation, KeyValue $settings):array
    {
        $list = [];
        $variationAttributes = [];

        foreach($variation['data']['attributes'] as $attributeValue)
        {
            if(array_key_exists($attributeValue['attributeId'], $this->linkedAttributeList) && strlen($this->linkedAttributeList[$attributeValue['attributeId']]) > 0)
            {
                if (strlen($this->attributeValueCache[$attributeValue['valueId']]) > 0)
                {
                    $variationAttributes[$this->linkedAttributeList[$attributeValue['attributeId']]][] = $this->attributeValueCache[$attributeValue['valueId']];
                }
            }
        }

        $typeList = array(
            self::CHARACTER_TYPE_COLOR,
            self::CHARACTER_TYPE_SIZE,
            self::CHARACTER_TYPE_PATTERN,
            self::CHARACTER_TYPE_MATERIAL
        );

        foreach ($typeList as $type)
        {
            $property = $this->getProperty($variation, $type, $settings);
            if (strlen(trim($property)) > 0)
            {
                $list[$type] = trim($property);
            }
            elseif (strlen(trim($variationAttributes[$type][0])) > 0)
            {
                $list[$type] = trim($variationAttributes[$type][0]);
            }
            else
            {
                $list[$type] = '';
            }
        }

        return $list;
    }

    /**
     * Get Google linked attribute list.
	 *
     * @param KeyValue $settings
     */
    private function loadLinkedAttributeList(KeyValue $settings)
    {
        $page = 1;
        $totalCount = null;

        $attributeList = $this->attributeRepository->all();

        if($attributeList instanceof PaginatedResult)
        {
            $totalCount = $attributeList->getTotalCount();

            // pagination iteration
            while($totalCount > 0)
            {
                $this->iterateAttributeList($attributeList, $settings);

                $page++;
                $totalCount = $totalCount - 50;

                $attributeList = $this->attributeRepository->all(['*'], 50, $page);
            }
        }
    }

    /**
	 * Iterates threw an attribute list and prepares the attribute values for the
	 * class cache.
	 *
     * @param PaginatedResult $attributeList
     * @param KeyValue $settings
     */
    private function iterateAttributeList($attributeList, $settings)
    {
        foreach($attributeList->getResult() as $attribute)
        {
            $attributeValuePage = 1;
            $attributeValueTotal = null;

            if($attribute instanceof Attribute)
            {
                if(strlen($attribute->googleShoppingAttribute) > 0)
                {
                    $this->linkedAttributeList[$attribute->id] = $attribute->googleShoppingAttribute;

                    $attributeValueList = $this->attributeValueRepository->findByAttributeId($attribute->id, $attributeValuePage);
                    if($attributeValueList instanceof PaginatedResult)
                    {
                        $attributeValueTotal = $attributeValueList->getTotalCount();
                        while($attributeValueTotal > 0)
                        {
                            $this->setAttributeValueToCache($attributeValueList, $settings);
                            $attributeValuePage++;
                            $attributeValueTotal = $attributeValueTotal - 50;
                            $attributeValueList = $this->attributeValueRepository->findByAttributeId($attribute->id, $attributeValuePage);
                        }
                    }
                }
            }
        }
    }

    /**
     * @param PaginatedResult $attibuteValueList
     * @param KeyValue $settings
     */
    private function setAttributeValueToCache($attributeValueList, $settings)
    {
        if($attributeValueList instanceof PaginatedResult)
        {
            foreach ($attributeValueList->getResult() as $attributeValue)
            {
                $attributeValueName = $this->attributeValueNameRepository->findOne($attributeValue->id, $settings->get('lang'));

                if($attributeValueName instanceof AttributeValueName)
                {
                    $this->attributeValueCache[$attributeValue->id] = $attributeValueName->name;
                }
            }
        }
    }

    /**
     * @param RecordList $idlResultList
     */
    private function createIdlArray($idlResultList)
    {
        if($idlResultList instanceof RecordList)
        {
            foreach($idlResultList as $idlVariation)
            {
                if($idlVariation instanceof Record)
                {
                    $this->idlVariations[$idlVariation->variationBase->id] = [
                        'itemBase.id' => $idlVariation->itemBase->id,
                        'variationBase.id' => $idlVariation->variationBase->id,
                        'itemPropertyList' => $idlVariation->itemPropertyList,
                        'variationStock.stockNet' => $idlVariation->variationStock->stockNet,
                        'variationRetailPrice.price' => $idlVariation->variationRetailPrice->price,
                        'variationRetailPrice.vatValue' => $idlVariation->variationRetailPrice->vatValue,
                        'variationRecommendedRetailPrice.price' => $idlVariation->variationRecommendedRetailPrice->price,
                        'variationSpecialOfferRetailPrice.retailPrice' => $idlVariation->variationSpecialOfferRetailPrice->retailPrice
                    ];
                }
            }
        }
    }
}