<?php
declare(strict_types=1);
/**
 * Sync Handler Model
 *
 * PHP version 7
 *
 * @category  Rain2o
 * @package   Rain2o_CatalogSync
 * @author    Joel Rainwater <joel.rain2o@gmail.com>
 * @copyright 2020 Joel Rainwater
 * @license   MIT License see LICENSE file
 */
namespace Rain2o\CatalogSync\Model;

use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Api\Data\CategoryInterfaceFactory;
use Magento\Catalog\Api\Data\ProductInterfaceFactory;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Model\ResourceModel\Db\ObjectRelationProcessor;
use Magento\Framework\Model\ResourceModel\Db\TransactionManagerInterface;
// use Magento\CatalogImportExport\Model\Import\Product as ProductImport;
// use Rain2o\CatalogSync\Api\ConfigInterface;
use Rain2o\CatalogSync\Api\MagentoServiceInterface;
use Rain2o\CatalogSync\Model\Import\Product as ProductImport;
use Rain2o\CatalogSync\Model\Source\Behavior;

class SyncHandler implements \Rain2o\CatalogSync\Api\SyncHandlerInterface
{
    /**
     * @var CategoryRepositoryInterface
     */
    private $categoryRepository;

    /**
     * @var CategoryInterfaceFactory
     */
    private $categoryFactory;

    /**
     * @var ProductRepositoryInterface
     */
    private $productRepository;

    /**
     * @var ProductInterfaceFactory
     */
    private $productFactory;

    /**
     * @var MagentoServiceInterface
     */
    private $service;

    /**
     * @var ProductImport
     */
    private $productImport;

    /**
     * @var TransactionManagerInterface
     */
    private $transactionManager;

    /**
     * @var ObjectRelationProcessor
     */
    private $objectRelationProcessor;

    /**
     * Include the images during the import
     *
     * @var bool
     */
    private $includeImages = false;

    /**
     * Which behavior to follow during execution
     *
     * @var string
     */
    private $behavior;

    public function __construct(
        CategoryRepositoryInterface $categoryRepository,
        CategoryInterfaceFactory $categoryFactory,
        ProductRepositoryInterface $productRepository,
        ProductInterfaceFactory $productFactory,
        MagentoServiceInterface $magentoService,
        ProductImport $productImport,
        // TransactionManagerInterface $transactionManager,
        // ObjectRelationProcessor $objectRelationProcessor,
        \Psr\Log\LoggerInterface $logger
    ) {
        $this->categoryRepository = $categoryRepository;
        $this->categoryFactory = $categoryFactory;
        $this->productRepository = $productRepository;
        $this->productFactory = $productFactory;
        $this->service = $magentoService;
        $this->productImport = $productImport;
        // $this->transactionManager = $transactionManager;
        // $this->objectRelationProcessor = $objectRelationProcessor;
        $this->logger = $logger;
    }

    public function execute(string $behavior, bool $includeImages = false)
    {
        $this->logger->info("== Executing SyncHandler ==");
        $this->includeImages = $includeImages;
        $this->behavior = $behavior;
        // @see Magento\CatalogImportExport\Model\Import\Product::_importData()
        // @todo Delete
        // if ($this->behavior === Behavior::BEHAVIOR_REPLACE) {
        //     // @todo Handle deleting images?
        //     $this->logger->info("Deleting products first...");
        //     $this->productImport->deleteProductsForReplacement();
        // }
        // @todo import @see Magento\CatalogImportExport\Model\Import\Product::_saveProductsData()
        // @todo Handle products per store scope right???
        $this->importProducts();
        // @todo with images - @see source/vendor/magento/module-import-export/Model/Import.php:473
    }

    public function importCategories()
    {
        # code...
    }

    public function importProducts()
    {
        $this->logger->info("== importProducts ==");
        $currentPage = 1;
        while ($productsToImport = $this->service->getAllProducts($currentPage++)) {
            // @todo @see Magento\ImportExport\Controller\Adminhtml\Import\Start
            $this->logger->info("== Batch #$currentPage (minus 1) ==");

            $this->productImport->execute($productsToImport, $this->behavior, $this->service->getMediaUrl());

            // // @todo Handle instert vs update
            // // product_entity columns
            // // entity_id, attribute_set_id, type_id, sku, has_options, required_options, created_at, updated_at
            // $entityRowsIn = [];

            // foreach ($productsToImport as $productToImport) {
            //     // map custom options from array of arrays to an assoc. array
            //     $customAttributes = array_reduce(
            //         $productToImport['custom_attributes'],
            //         function ($result, $option) {
            //             $result[$option['attribute_code']] = $option['value'];
            //             return $result;
            //         },
            //         []
            //     );

            //     $entityRowsIn[strtolower($productToImport['sku'])] = [
            //         'attribute_set_id' => $productToImport['attribute_set_id'],
            //         'type_id' => $productToImport['type_id'],
            //         'sku' => $productToImport['sku'],
            //         'has_options' => $customAttributes['has_options'] ?? 0,
            //         'required_options' => $customAttributes['required_options'] ?? 0,
            //         'created_at' => $productToImport['created_at'],
            //         'updated_at' => $productToImport['updated_at'],
            //     ];
            // }

            // // @see source/vendor/magento/module-catalog-import-export/Model/Import/Product.php _saveProducts()
            // $this->productImport->saveProductEntity($entityRowsIn, []);
            // // @todo All of these extra calls
            // // $this->saveProductEntity(
            // //     $entityRowsIn,
            // //     $entityRowsUp
            // // )->_saveProductWebsites(
            // //     $this->websitesCache
            // // )->_saveProductCategories(
            // //     $this->categoriesCache
            // // )->_saveProductTierPrices(
            // //     $tierPrices
            // // )->_saveMediaGallery(
            // //     $mediaGallery
            // // )->_saveProductAttributes(
            // //     $attributes
            // // )->updateMediaGalleryVisibility(
            // //     $imagesForChangeVisibility
            // // )->updateMediaGalleryLabels(
            // //     $labelsForUpdate
            // // );

            // // foreach ($productsToImport as $productToImport) {
            // //     $existingProduct = $this->getProduct($productToImport["sku"]);
            // //     $orignalData = $existingProduct->getData();
            // //     // @todo exclude anything?
            // //     unset($productToImport["id"]);
            // //     if (isset($productToImport["extension_attributes"])) {
            // //         $extensionAttributesData = $productToImport["extension_attributes"];
            // //         $extensionAttributes = $this->extensionAttributesFactory->create(
            // //             ProductInterface::class,
            // //             $extensionAttributesData
            // //         );
            // //         $productToImport["extension_attributes"] = $extensionAttributes;
            // //         // unset($productToImport["extension_attributes"]);
            // //     }
            // //     $existingProduct->setData($productToImport);
            // //     // @todo Can we bulk save products?
            // //     try {
            // //         $this->logger->info("Saving product " . $productToImport["sku"]);
            // //         $this->productRepository->save($existingProduct);
            // //     } catch (LocalizedException $e) {
            // //         $message = $e->getMessage();
            // //         $sku = $productToImport["sku"];
            // //         $this->logger->error("Failed...");
            // //         $this->logger->error("New Product Data: ");
            // //         // $this->logger->error(print_r($productToImport, true));
            // //         $this->logger->error(print_r($existingProduct->getData(), true));
            // //         $this->logger->error("Original Product Data: ");
            // //         $this->logger->error(print_r($orignalData, true));
            // //         throw new LocalizedException(
            // //             __("Failed on SKU $sku. Error: $message")
            // //         );
            // //     }
            // // }
        }
    }

    // /**
    //  * Get skus data.
    //  *
    //  * @return array
    //  */
    // private function getExistingSkus()
    // {
    //     $oldSkus = [];
    //     $columns = ['entity_id', 'type_id', 'attribute_set_id', 'sku'];
    //     if ($this->getProductEntityLinkField() != $this->getProductIdentifierField()) {
    //         $columns[] = $this->getProductEntityLinkField();
    //     }
    //     foreach ($this->productFactory->create()->getProductEntitiesInfo($columns) as $info) {
    //         $typeId = $info['type_id'];
    //         $sku = strtolower($info['sku']);
    //         $oldSkus[$sku] = [
    //             'type_id' => $typeId,
    //             'attr_set_id' => $info['attribute_set_id'],
    //             'entity_id' => $info['entity_id'],
    //             'supported_type' => isset($this->productTypeModels[$typeId]),
    //             $this->getProductEntityLinkField() => $info[$this->getProductEntityLinkField()],
    //         ];
    //     }
    //     return $oldSkus;
    // }

    // /**
    //  * Get Product instance
    //  * Either a new empty product or an existing one if found
    //  *
    //  * @param string $sku
    //  *
    //  * @return \Magento\Catalog\Api\Data\ProductInterface
    //  */
    // private function getProduct(string $sku): ProductInterface
    // {
    //     if ($this->behavior === Behavior::BEHAVIOR_REPLACE) {
    //         $product = $this->productFactory->create();
    //     } else {
    //         try {
    //             $product = $this->productRepository->get($sku);
    //         } catch (\Magento\Framework\Exception\NoSuchEntityException $e) {
    //             $product = $this->productFactory->create();
    //         }
    //     }
    //     return $product;
    // }
}
