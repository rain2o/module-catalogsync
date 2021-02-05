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

use GuzzleHttp\Client;
use GuzzleHttp\ClientFactory;
use GuzzleHttp\Psr7\Response;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\Webapi\Rest\Request;
use Rain2o\CatalogSync\Api\ConfigInterface;

class MagentoService implements \Rain2o\CatalogSync\Api\MagentoServiceInterface
{
    const VERSION     = 'V1';
    const URL_PATTERN = "%s/rest/all/%s/";
    const PAGE_SIZE   = 20;
    const MEDIA_PATH  = 'media/catalog/';

    /**
     * @var ConfigInterface
     */
    private $config;

    /**
     * @var ClientFactory
     */
    private $clientFactory;

    /**
     * @var Json
     */
    private $serializer;

    /**
     * Client instance
     *
     * @var Client
     */
    private $client;

    /**
     * Base API URL
     *
     * @var string
     */
    private $baseUrl;

    /**
     * API Access Token
     *
     * @var string
     */
    private $accessToken;

    public function __construct(
        ConfigInterface $config,
        ClientFactory $clientFactory,
        Json $serializer
    ) {
        $this->config = $config;
        $this->clientFactory = $clientFactory;
        $this->serializer = $serializer;
    }

    /**
     * Get All Products (limited to PAGE_SIZE)
     *
     * @param int $currentPage
     *
     * @return array of Products
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function getAllProducts($currentPage = 1): array
    {
        /**
         * This behavior is to account for an issue with how Magento handles API pagination
         * backwards compatibility for version prior to 2.4
         * @see https://github.com/magento/magento2/issues/8099
         */
        if (isset($this->totalCount['products']) &&
            (($currentPage - 1) * self::PAGE_SIZE) > $this->totalCount['products']
        ) {
            return [];
        }

        $params = [
            "searchCriteria" => [
                "pageSize" => self::PAGE_SIZE,
                "currentPage" => $currentPage
            ]
        ];
        $response = $this->doRequest("products", $params);
        $responseBody = $this->serializer->unserialize(
            $response->getBody()->getContents()
        );

        // set total count if not set already
        if (!isset($this->totalCount['products'])) {
            $this->totalCount['products'] = $responseBody['total_count'];
        }
        return $responseBody['items'] ?? [];
    }

    /**
     * Get Media URL
     *
     * @return string
     */
    public function getMediaUrl(): string
    {
        return $this->getBaseUrl() . self::MEDIA_PATH;
    }

    /**
     * Make the HTTP Request
     *
     * @param string $endpoint
     * @param array $query
     *
     * @return \GuzzleHttp\Psr7\Response
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    private function doRequest(string $endpoint, array $query = []): Response
    {
        $token = $this->getAccessToken();
        $options = ['query' => $query];
        $options['headers'] = [
            "Authorization" => "Bearer $token",
            "Accept"        => "application/json",
        ];

        return $this->getClient()->request(
            Request::HTTP_METHOD_GET,
            $endpoint,
            $options
        );
    }

    /**
     * Get Base URL for API calls
     *
     * @return string
     */
    private function getBaseUrl(): string
    {
        if (!$this->baseUrl) {
            $baseUrl = $this->config->getBaseUrl();
            $this->baseUrl = sprintf(
                self::URL_PATTERN,
                $baseUrl,
                self::VERSION
            );
        }
        return $this->baseUrl;
    }

    /**
     * Get API Access Token
     *
     * @return string
     */
    private function getAccessToken(): string
    {
        if (!$this->accessToken) {
            $this->accessToken = $this->config->getAccessToken();
        }
        return $this->accessToken;
    }

    /**
     * Get Client instance
     *
     * @return \GuzzleHttp\Client
     */
    private function getClient(): Client
    {
        if (!$this->client) {
            $this->client = $this->clientFactory->create(['config' => [
                'base_uri' => $this->getBaseUrl()
            ]]);
        }
        return $this->client;
    }
}
