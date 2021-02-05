<?php
declare(strict_types=1);
/**
 * Configuration Interface
 *
 * PHP version 7
 *
 * @category  Rain2o
 * @package   Rain2o_CatalogSync
 * @author    Joel Rainwater <joel.rain2o@gmail.com>
 * @copyright 2020 Joel Rainwater
 * @license   MIT License see LICENSE file
 */
namespace Rain2o\CatalogSync\Api;

interface ConfigInterface
{
    /**
     * Get Base URL
     *
     * @param int|null $storeId
     *
     * @return string|null
     */
    public function getBaseUrl($storeId = null): ?string;

    /**
     * Get Access Token
     *
     * @param int|null $storeId
     *
     * @return string|null
     */
    public function getAccessToken($storeId = null): ?string;
}
