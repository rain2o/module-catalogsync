<?php
declare(strict_types=1);
/**
 * Sync Controller
 *
 * PHP version 7
 *
 * @category  Rain2o
 * @package   Rain2o_CatalogSync
 * @author    Joel Rainwater <joel.rain2o@gmail.com>
 * @copyright 2020 Joel Rainwater
 * @license   MIT License see LICENSE file
 */
namespace Rain2o\CatalogSync\Controller\Adminhtml\Sync;

use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface as HttpPostActionInterface;
use Magento\Framework\Controller\ResultFactory;
use Rain2o\CatalogSync\Api\SyncHandlerInterface;

class Sync extends \Magento\Backend\App\Action implements HttpPostActionInterface
{
    /**
     * Authorization level of a basic admin session
     *
     * @see _isAllowed()
     */
    const ADMIN_RESOURCE = 'Rain2o_CatalogSync::sync';

    /**
     * @var SyncHandlerInterface
     */
    private $syncHandler;

    public function __construct(Context $context, SyncHandlerInterface $syncHandler)
    {
        parent::__construct($context);
        $this->syncHandler = $syncHandler;
    }

    /**
     * Import and export Page
     * @todo Handle timeout
     *
     * @return \Magento\Backend\Model\View\Result\Page
     */
    public function execute()
    {
        // print_r("== Testing ==");
        // $importRatesFile = $this->getRequest()->getFiles('import_rates_file');
        if ($this->getRequest()->isPost()) {
            $behavior = $this->getRequest()->getPostValue('behavior');
            $includeImages = (bool) $this->getRequest()->getPostValue('include_images', 0);
            try {
                // /** @var $importHandler \Magento\TaxImportExport\Model\Rate\CsvImportHandler */
                // $importHandler = $this->_objectManager->create(
                //     \Magento\TaxImportExport\Model\Rate\CsvImportHandler::class
                // );
                // $importHandler->importFromCsvFile($importRatesFile);
                $errorMessages = $this->syncHandler->execute($behavior, $includeImages);

                if (count($errorMessages) > 0) {
                    foreach ($errorMessages as $message) {
                        $this->messageManager->addError($message);
                    }
                } else {
                    $this->messageManager->addSuccess(
                        __("Importing catalog with behavior $behavior. Including Images: " . ($includeImages ? "yes": "no"))
                    );
                }
            } catch (\Magento\Framework\Exception\LocalizedException $e) {
                $this->messageManager->addError($e->getMessage());
            } catch (\Exception $e) {
                $this->messageManager->addError($e->getMessage());
                // $this->messageManager->addError(__('Invalid file upload attempt'));
            }
        } else {
            $this->messageManager->addError(__('Invalid file upload attempt'));
        }
        /** @var \Magento\Backend\Model\View\Result\Redirect $resultRedirect */
        $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
        $resultRedirect->setUrl($this->_redirect->getRedirectUrl());
        return $resultRedirect;
    }
}
