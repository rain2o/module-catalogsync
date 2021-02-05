<?php
declare(strict_types=1);
/**
 * Behavior Source Model
 *
 * PHP version 7
 *
 * @category  Rain2o
 * @package   Rain2o_CatalogSync
 * @author    Joel Rainwater <joel.rain2o@gmail.com>
 * @copyright 2020 Joel Rainwater
 * @license   MIT License see LICENSE file
 */
namespace Rain2o\CatalogSync\Model\Source;

/**
 * Behavior Source Model
 */
class Behavior implements \Magento\Framework\Data\OptionSourceInterface
{

    /**
     * Behavior Values
     */
    const BEHAVIOR_UPDATE  = 'update';
    const BEHAVIOR_REPLACE = 'replace';

    /**#@-*/

    /**
     * Retrieve option array
     *
     * @return array
     */
    public function toOptionArray()
    {
        return [
            [
                'label' => __('Add/Update'),
                'value' => self::BEHAVIOR_UPDATE
            ],
            [
                'label' => __('Replace'),
                'value' => self::BEHAVIOR_REPLACE
            ]
        ];
    }

    /**
     * Retrieve option array
     *
     * @return array
     */
    public static function getFilterOptionArray()
    {
        return [
            self::BEHAVIOR_UPDATE  => __('Add/Update'),
            self::BEHAVIOR_REPLACE => __('Replace')
        ];
    }
}
