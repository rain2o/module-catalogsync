<?php
declare(strict_types=1);
/**
 * Import Product Model
 *
 * PHP version 7
 *
 * @category  Rain2o
 * @package   Rain2o_CatalogSync
 * @author    Joel Rainwater <joel.rain2o@gmail.com>
 * @copyright 2020 Joel Rainwater
 * @license   MIT License see LICENSE file
 */
namespace Rain2o\CatalogSync\Model\Import;

use Rain2o\CatalogSync\Model\Source\Behavior;

/**
 * Import entity product model
 */
class Product extends \Magento\CatalogImportExport\Model\Import\Product
{
    /**
     * Array of product data to import
     *
     * @var array
     */
    protected $products;

    /**
     * Base URL for media
     *
     * @var string
     */
    protected $mediaUrl;

    /**
     * Product entity link field
     *
     * @var string
     */
    private $productEntityLinkField;

    public function execute(array $products, string $behavior, string $mediaUrl)
    {
        $this->setParameters(
            array_merge(
                $this->getParameters(),
                ['behavior' => $behavior]
            )
        );
        $this->setProducts($products);
        $this->setMediaUrl($mediaUrl);
        $this->_importData();
    }

    public function setProducts(array $products)
    {
        $this->products = $products;
    }

    public function getProducts(): array
    {
        return $this->products;
    }

    /**
     * Set Media URL for retrieving images
     *
     * @param string $mediaUrl
     *
     * @return void
     */
    public function setMediaUrl(string $mediaUrl)
    {
        // media url should include entity type
        $this->mediaUrl = $mediaUrl . 'product';
    }

    public function getMediaUrl(): string
    {
        return $this->mediaUrl;
    }

    /**
     * Delete products.
     *
     * @return $this
     * @throws \Exception
     */
    protected function _deleteProducts()
    {
        $productEntityTable = $this->_resourceFactory->create()->getEntityTable();

        $idsToDelete = array_map(function ($product) {
            return $product['id'];
        }, $this->getProducts());
        $this->countItemsDeleted += count($idsToDelete);
        $this->transactionManager->start($this->getConnection());
        try {
            $this->objectRelationProcessor->delete(
                $this->transactionManager,
                $this->getConnection(),
                $productEntityTable,
                $this->getConnection()->quoteInto('entity_id IN (?)', $idsToDelete),
                ['entity_id' => $idsToDelete]
            );
            $this->_eventManager->dispatch(
                'catalog_product_import_bunch_delete_commit_before',
                [
                    'adapter' => $this,
                    'bunch' => $this->getProducts(),
                    'ids_to_delete' => $idsToDelete,
                ]
            );
            $this->transactionManager->commit();
        } catch (\Exception $e) {
            $this->transactionManager->rollBack();
            throw $e;
        }

        $this->_eventManager->dispatch(
            'catalog_product_import_bunch_delete_after',
            ['adapter' => $this, 'bunch' => $this->getProducts()]
        );

        return $this;
    }

    /**
     * Save products data.
     *
     * @return $this
     */
    protected function _saveProductsData()
    {
        // @todo Do I need to override/modify this?
        $this->_saveProducts();
        foreach ($this->_productTypeModels as $productTypeModel) {
            $productTypeModel->saveData();
        }
        $this->_saveLinks();
        $this->_saveStockItem();
        if ($this->_replaceFlag) {
            $this->getOptionEntity()->clearProductsSkuToId();
        }
        $this->getOptionEntity()->importData();

        return $this;
    }

    /**
     * Gather and save information about product entities.
     *
     * @return $this
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     * @SuppressWarnings(PHPMD.UnusedLocalVariable)
     * @throws LocalizedException
     * phpcs:disable Generic.Metrics.NestingLevel.TooHigh
     */
    protected function _saveProducts()
    {
        // @todo fix this - PHP Notice:  Undefined index: group_2kla in /var/www/magento2/vendor/magento/module-bundle-import-export/Model/Import/Product/Type/Bundle.php on line 406
        $priceIsGlobal = $this->_catalogData->isPriceGlobal();
        $productLimit = null;
        $productsQty = null;
        $entityLinkField = $this->getProductEntityLinkField();
        $bunch = $this->getProducts();

        $entityRowsIn = [];
        $entityRowsUp = [];
        $attributes = [];
        $this->websitesCache = [];
        $this->categoriesCache = [];
        $tierPrices = [];
        $mediaGallery = [];
        $labelsForUpdate = [];
        $imagesForChangeVisibility = [];
        $uploadedImages = [];
        $previousType = null;
        $prevAttributeSet = null;
        $existingImages = $this->getExistingImages($bunch);

        foreach ($bunch as $rowNum => $rowData) {
            // reset category processor's failed categories array
            $this->categoryProcessor->clearFailedCategories();

            if (!$this->validateRow($rowData, $rowNum)) {
                continue;
            }
            if ($this->getErrorAggregator()->hasToBeTerminated()) {
                $this->getErrorAggregator()->addRowToSkip($rowNum);
                continue;
            }
            $rowScope = $this->getRowScope($rowData);

            $urlKey = $this->getUrlKey($rowData);
            if (!empty($rowData[self::URL_KEY])) {
                // If url_key column and its value were in the CSV file
                $rowData[self::URL_KEY] = $urlKey;
            } elseif ($this->isNeedToChangeUrlKey($rowData)) {
                // If url_key column was empty or even not declared in the CSV file but by the rules it is need to
                // be setteed. In case when url_key is generating from name column we have to ensure that the bunch
                // of products will pass for the event with url_key column.
                $bunch[$rowNum][self::URL_KEY] = $rowData[self::URL_KEY] = $urlKey;
            }

            $rowSku = $rowData[self::COL_SKU];

            if (null === $rowSku) {
                $this->getErrorAggregator()->addRowToSkip($rowNum);
                continue;
            }

            $storeId = !empty($rowData[self::COL_STORE])
                ? $this->getStoreIdByCode($rowData[self::COL_STORE])
                : Store::DEFAULT_STORE_ID;
            $rowExistingImages = $existingImages[$storeId][$rowSku] ?? [];
            $rowStoreMediaGalleryValues = $rowExistingImages;
            $rowExistingImages += $existingImages[Store::DEFAULT_STORE_ID][$rowSku] ?? [];

            if (self::SCOPE_STORE == $rowScope) {
                // set necessary data from SCOPE_DEFAULT row
                $rowData[self::COL_TYPE] = $this->skuProcessor->getNewSku($rowSku)['type_id'];
                $rowData['attribute_set_id'] = $this->skuProcessor->getNewSku($rowSku)['attr_set_id'];
                $rowData[self::COL_ATTR_SET] = $this->skuProcessor->getNewSku($rowSku)['attr_set_code'];
            }

            // 1. Entity phase
            if ($this->isSkuExist($rowSku)) {
                // existing row
                if (isset($rowData['attribute_set_code'])) {
                    $attributeSetId = $this->catalogConfig->getAttributeSetId(
                        $this->getEntityTypeId(),
                        $rowData['attribute_set_code']
                    );

                    // wrong attribute_set_code was received
                    if (!$attributeSetId) {
                        throw new LocalizedException(
                            __(
                                'Wrong attribute set code "%1", please correct it and try again.',
                                $rowData['attribute_set_code']
                            )
                        );
                    }
                } else {
                    $attributeSetId = $this->skuProcessor->getNewSku($rowSku)['attr_set_id'];
                }

                $entityRowsUp[] = [
                    'updated_at' => (new \DateTime())->format(DateTime::DATETIME_PHP_FORMAT),
                    'attribute_set_id' => $attributeSetId,
                    $entityLinkField => $this->getExistingSku($rowSku)[$entityLinkField]
                ];
            } else {
                if (!$productLimit || $productsQty < $productLimit) {
                    $entityRowsIn[strtolower($rowSku)] = [
                        'attribute_set_id' => $this->skuProcessor->getNewSku($rowSku)['attr_set_id'],
                        'type_id' => $this->skuProcessor->getNewSku($rowSku)['type_id'],
                        'sku' => $rowSku,
                        'has_options' => isset($rowData['has_options']) ? $rowData['has_options'] : 0,
                        'created_at' => (new \DateTime())->format(DateTime::DATETIME_PHP_FORMAT),
                        'updated_at' => (new \DateTime())->format(DateTime::DATETIME_PHP_FORMAT),
                    ];
                    $productsQty++;
                } else {
                    $rowSku = null;
                    // sign for child rows to be skipped
                    $this->getErrorAggregator()->addRowToSkip($rowNum);
                    continue;
                }
            }

            if (!array_key_exists($rowSku, $this->websitesCache)) {
                $this->websitesCache[$rowSku] = [];
            }
            // 2. Product-to-Website phase
            if (!empty($rowData[self::COL_PRODUCT_WEBSITES])) {
                $websiteCodes = explode($this->getMultipleValueSeparator(), $rowData[self::COL_PRODUCT_WEBSITES]);
                foreach ($websiteCodes as $websiteCode) {
                    $websiteId = $this->storeResolver->getWebsiteCodeToId($websiteCode);
                    $this->websitesCache[$rowSku][$websiteId] = true;
                }
            } else {
                $product = $this->retrieveProductBySku($rowSku);
                if ($product) {
                    $websiteIds = $product->getWebsiteIds();
                    foreach ($websiteIds as $websiteId) {
                        $this->websitesCache[$rowSku][$websiteId] = true;
                    }
                }
            }

            // 3. Categories phase
            if (!array_key_exists($rowSku, $this->categoriesCache)) {
                $this->categoriesCache[$rowSku] = [];
            }
            $rowData['rowNum'] = $rowNum;
            $categoryIds = $this->processRowCategories($rowData);
            foreach ($categoryIds as $id) {
                $this->categoriesCache[$rowSku][$id] = true;
            }
            unset($rowData['rowNum']);

            // 4.1. Tier prices phase
            if (!empty($rowData['_tier_price_website'])) {
                $tierPrices[$rowSku][] = [
                    'all_groups' => $rowData['_tier_price_customer_group'] == self::VALUE_ALL,
                    'customer_group_id' => $rowData['_tier_price_customer_group'] ==
                    self::VALUE_ALL ? 0 : $rowData['_tier_price_customer_group'],
                    'qty' => $rowData['_tier_price_qty'],
                    'value' => $rowData['_tier_price_price'],
                    'website_id' => self::VALUE_ALL == $rowData['_tier_price_website'] ||
                    $priceIsGlobal ? 0 : $this->storeResolver->getWebsiteCodeToId($rowData['_tier_price_website']),
                ];
            }

            if (!$this->validateRow($rowData, $rowNum)) {
                continue;
            }

            // 5. Media gallery phase
            list($rowImages, $rowLabels) = $this->getImagesFromRow($rowData);
            $imageHiddenStates = $this->getImagesHiddenStates($rowData);
            foreach (array_keys($imageHiddenStates) as $image) {
                //Mark image as uploaded if it exists
                if (array_key_exists($image, $rowExistingImages)) {
                    $uploadedImages[$image] = $image;
                }
                //Add image to hide to images list if it does not exist
                if (empty($rowImages[self::COL_MEDIA_IMAGE])
                    || !in_array($image, $rowImages[self::COL_MEDIA_IMAGE])
                ) {
                    $rowImages[self::COL_MEDIA_IMAGE][] = $image;
                }
            }

            $rowData[self::COL_MEDIA_IMAGE] = [];

            /*
                * Note: to avoid problems with undefined sorting, the value of media gallery items positions
                * must be unique in scope of one product.
                */
            $position = 0;
            foreach ($rowImages as $column => $columnImages) {
                foreach ($columnImages as $columnImageKey => $columnImage) {
                    if (!isset($uploadedImages[$columnImage])) {
                        $uploadedFile = $this->uploadMediaFiles(
                            $this->getMediaUrl() . $columnImage
                        );
                        $uploadedFile = $uploadedFile ?: $this->getSystemFile($columnImage);
                        if ($uploadedFile) {
                            $uploadedImages[$columnImage] = $uploadedFile;
                        } else {
                            unset($rowData[$column]);
                            $this->addRowError(
                                ValidatorInterface::ERROR_MEDIA_URL_NOT_ACCESSIBLE,
                                $rowNum,
                                null,
                                null,
                                ProcessingError::ERROR_LEVEL_NOT_CRITICAL
                            );
                        }
                    } else {
                        $uploadedFile = $uploadedImages[$columnImage];
                    }

                    if ($uploadedFile && $column !== self::COL_MEDIA_IMAGE) {
                        $rowData[$column] = $uploadedFile;
                    }

                    if (!$uploadedFile || isset($mediaGallery[$storeId][$rowSku][$uploadedFile])) {
                        continue;
                    }

                    if (isset($rowExistingImages[$uploadedFile])) {
                        $currentFileData = $rowExistingImages[$uploadedFile];
                        $currentFileData['store_id'] = $storeId;
                        $storeMediaGalleryValueExists = isset($rowStoreMediaGalleryValues[$uploadedFile]);
                        if (array_key_exists($uploadedFile, $imageHiddenStates)
                            && $currentFileData['disabled'] != $imageHiddenStates[$uploadedFile]
                        ) {
                            $imagesForChangeVisibility[] = [
                                'disabled' => $imageHiddenStates[$uploadedFile],
                                'imageData' => $currentFileData,
                                'exists' => $storeMediaGalleryValueExists
                            ];
                            $storeMediaGalleryValueExists = true;
                        }

                        if (isset($rowLabels[$column][$columnImageKey])
                            && $rowLabels[$column][$columnImageKey] !=
                            $currentFileData['label']
                        ) {
                            $labelsForUpdate[] = [
                                'label' => $rowLabels[$column][$columnImageKey],
                                'imageData' => $currentFileData,
                                'exists' => $storeMediaGalleryValueExists
                            ];
                        }
                    } else {
                        if ($column == self::COL_MEDIA_IMAGE) {
                            $rowData[$column][] = $uploadedFile;
                        }
                        $mediaGallery[$storeId][$rowSku][$uploadedFile] = [
                            'attribute_id' => $this->getMediaGalleryAttributeId(),
                            'label' => isset($rowLabels[$column][$columnImageKey])
                                ? $rowLabels[$column][$columnImageKey]
                                : '',
                            'position' => ++$position,
                            'disabled' => isset($imageHiddenStates[$columnImage])
                                ? $imageHiddenStates[$columnImage] : '0',
                            'value' => $uploadedFile,
                        ];
                    }
                }
            }

            // 6. Attributes phase
            $rowStore = (self::SCOPE_STORE == $rowScope)
                ? $this->storeResolver->getStoreCodeToId($rowData[self::COL_STORE])
                : 0;
            $productType = isset($rowData[self::COL_TYPE]) ? $rowData[self::COL_TYPE] : null;
            if ($productType !== null) {
                $previousType = $productType;
            }
            if (isset($rowData[self::COL_ATTR_SET])) {
                $prevAttributeSet = $rowData[self::COL_ATTR_SET];
            }
            if (self::SCOPE_NULL == $rowScope) {
                // for multiselect attributes only
                if ($prevAttributeSet !== null) {
                    $rowData[self::COL_ATTR_SET] = $prevAttributeSet;
                }
                if ($productType === null && $previousType !== null) {
                    $productType = $previousType;
                }
                if ($productType === null) {
                    continue;
                }
            }

            $productTypeModel = $this->_productTypeModels[$productType];
            if (!empty($rowData['tax_class_name'])) {
                $rowData['tax_class_id'] =
                    $this->taxClassProcessor->upsertTaxClass($rowData['tax_class_name'], $productTypeModel);
            }

            if ($this->getBehavior() == Import::BEHAVIOR_APPEND ||
                empty($rowData[self::COL_SKU])
            ) {
                $rowData = $productTypeModel->clearEmptyData($rowData);
            }

            $rowData = $productTypeModel->prepareAttributesWithDefaultValueForSave(
                $rowData,
                !$this->isSkuExist($rowSku)
            );
            $product = $this->_proxyProdFactory->create(['data' => $rowData]);

            foreach ($rowData as $attrCode => $attrValue) {
                $attribute = $this->retrieveAttributeByCode($attrCode);

                if ('multiselect' != $attribute->getFrontendInput() && self::SCOPE_NULL == $rowScope) {
                    // skip attribute processing for SCOPE_NULL rows
                    continue;
                }
                $attrId = $attribute->getId();
                $backModel = $attribute->getBackendModel();
                $attrTable = $attribute->getBackend()->getTable();
                $storeIds = [0];

                if ('datetime' == $attribute->getBackendType()
                    && (
                        in_array($attribute->getAttributeCode(), $this->dateAttrCodes)
                        || $attribute->getIsUserDefined()
                    )
                ) {
                    $attrValue = $this->dateTime->formatDate($attrValue, false);
                } elseif ('datetime' == $attribute->getBackendType() && strtotime($attrValue)) {
                    $attrValue = gmdate(
                        'Y-m-d H:i:s',
                        $this->_localeDate->date($attrValue)->getTimestamp()
                    );
                } elseif ($backModel) {
                    $attribute->getBackend()->beforeSave($product);
                    $attrValue = $product->getData($attribute->getAttributeCode());
                }
                if (self::SCOPE_STORE == $rowScope) {
                    if (self::SCOPE_WEBSITE == $attribute->getIsGlobal()) {
                        // check website defaults already set
                        if (!isset($attributes[$attrTable][$rowSku][$attrId][$rowStore])) {
                            $storeIds = $this->storeResolver->getStoreIdToWebsiteStoreIds($rowStore);
                        }
                    } elseif (self::SCOPE_STORE == $attribute->getIsGlobal()) {
                        $storeIds = [$rowStore];
                    }
                    if (!$this->isSkuExist($rowSku)) {
                        $storeIds[] = 0;
                    }
                }
                foreach ($storeIds as $storeId) {
                    if (!isset($attributes[$attrTable][$rowSku][$attrId][$storeId])) {
                        $attributes[$attrTable][$rowSku][$attrId][$storeId] = $attrValue;
                    }
                }
                // restore 'backend_model' to avoid 'default' setting
                $attribute->setBackendModel($backModel);
            }
        }

        foreach ($bunch as $rowNum => $rowData) {
            if ($this->getErrorAggregator()->isRowInvalid($rowNum)) {
                unset($bunch[$rowNum]);
            }
        }

        $this->saveProductEntity(
            $entityRowsIn,
            $entityRowsUp
        )->_saveProductWebsites(
            $this->websitesCache
        )->_saveProductCategories(
            $this->categoriesCache
        )->_saveProductTierPrices(
            $tierPrices
        )->_saveMediaGallery(
            $mediaGallery
        )->_saveProductAttributes(
            $attributes
        )/*->updateMediaGalleryVisibility(
            $imagesForChangeVisibility
        )->updateMediaGalleryLabels(
            $labelsForUpdate
        )*/;

        $this->_eventManager->dispatch(
            'catalog_product_import_bunch_save_after',
            ['adapter' => $this, 'bunch' => $bunch]
        );

        return $this;
    }
    //phpcs:enable Generic.Metrics.NestingLevel

    /**
     * Get product entity link field
     *
     * @return string
     */
    private function getProductEntityLinkField()
    {
        if (!$this->productEntityLinkField) {
            $this->productEntityLinkField = $this->getMetadataPool()
                ->getMetadata(\Magento\Catalog\Api\Data\ProductInterface::class)
                ->getLinkField();
        }
        return $this->productEntityLinkField;
    }
}
