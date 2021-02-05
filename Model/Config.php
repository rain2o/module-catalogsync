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
namespace Rain2o\CatalogSync\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

/**
 * Configuration Model
 */
class Config implements \Rain2o\CatalogSync\Api\ConfigInterface
{
    const XML_PATH_BASE        = 'catalog_sync/%s/%s';

    const XML_GROUP_GENERAL    = 'general';
    const XML_KEY_BASE_URL     = 'base_url';
    const XML_KEY_ACCESS_TOKEN = 'access_token';

    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * Construct
     *
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     */
    public function __construct(ScopeConfigInterface $scopeConfig)
    {
        $this->scopeConfig = $scopeConfig;
    }

    /**
     * Retrieve information from system configuration
     *
     * @param string $group     Config Group
     * @param string $field     Field key
     * @param int|null $storeId Store ID
     *
     * @return mixed
     */
    private function getValue($group, $field, $storeId = null)
    {
        return $this->scopeConfig->getValue(
            sprintf(self::XML_PATH_BASE, $group, $field),
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    //region Group General Settings

    /**
     * @inheritDoc
     */
    public function getBaseUrl($storeId = null): ?string
    {
        return $this->getValue(
            self::XML_GROUP_GENERAL,
            self::XML_KEY_BASE_URL,
            $storeId
        );
    }

    /**
     * @inheritDoc
     */
    public function getAccessToken($storeId = null): ?string
    {
        return $this->getValue(
            self::XML_GROUP_GENERAL,
            self::XML_KEY_ACCESS_TOKEN,
            $storeId
        );
    }

    //endregion Group General Settings
}
