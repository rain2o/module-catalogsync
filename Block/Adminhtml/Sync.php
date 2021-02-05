<?php
declare(strict_types=1);
/**
 * Configuration Model
 *
 * PHP version 7
 *
 * @category  Rain2o
 * @package   Rain2o_CatalogSync
 * @author    Joel Rainwater <joel.rain2o@gmail.com>
 * @copyright 2020 Joel Rainwater
 * @license   MIT License see LICENSE file
 */
namespace Rain2o\CatalogSync\Block\Adminhtml;

use Magento\Backend\Block\Template\Context;
use Rain2o\CatalogSync\Model\Source\Behavior as BehaviorSource;

/**
 * Catalog Sync Block
 */
class Sync extends \Magento\Backend\Block\Widget
{
    /**
     * @var string
     */
    protected $_template = 'Rain2o_CatalogSync::sync.phtml';

    /**
     * @var BehaviorSource
     */
    private $behaviorSource;

    /**
     * @param \Magento\Backend\Block\Template\Context $context
     * @param \Rain2o\CatalogSync\Model\Source\Behavior $behaviorSource
     * @param array $data
     */
    public function __construct(
        Context $context,
        BehaviorSource $behaviorSource,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->setUseContainer(true);
        $this->behaviorSource = $behaviorSource;
    }

    /**
     * Get Behavior Options
     *
     * @return array
     */
    public function getBehaviorOptions(): array
    {
        return $this->behaviorSource->getFilterOptionArray();
    }

    /**
     * Get Notes for each behavior
     *
     * @return array
     */
    public function getBehaviorNotes(): array
    {
        return [
            BehaviorSource::BEHAVIOR_UPDATE => __(
                "New product data is added to the existing product data for the existing entries in the database. "
                . "All fields except sku can be updated."
            ),
            BehaviorSource::BEHAVIOR_REPLACE => __(
                "The existing product data is replaced with new data. <b>Exercise caution when replacing data "
                . "because the existing product data will be completely cleared and all references "
                . "in the system will be lost.</b>"
            ),
        ];
    }
}
