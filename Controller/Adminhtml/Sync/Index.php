<?php
declare(strict_types=1);
/**
 * Sync Index Controller
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

use Magento\Framework\App\Action\HttpGetActionInterface as HttpGetActionInterface;
use Magento\Framework\Controller\ResultFactory;

class Index extends \Magento\Backend\App\Action implements HttpGetActionInterface
{
    /**
     * Authorization level of a basic admin session
     *
     * @see _isAllowed()
     */
    const ADMIN_RESOURCE = 'Rain2o_CatalogSync::sync';

    /**
     * Import and export Page
     *
     * @return \Magento\Backend\Model\View\Result\Page
     */
    public function execute()
    {
        /** @var \Magento\Backend\Model\View\Result\Page $resultPage */
        $resultPage = $this->resultFactory->create(ResultFactory::TYPE_PAGE);

        $resultPage->setActiveMenu('Rain2o_CatalogSync::sync');
        $resultPage->addContent(
            $resultPage->getLayout()->createBlock(\Rain2o\CatalogSync\Block\Adminhtml\Sync::class)
        );
        $resultPage->getConfig()->getTitle()->prepend(__('Synchronize Catalog'));
        return $resultPage;
    }
}
