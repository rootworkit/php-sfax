<?php
/**
 * Client
 *
 * @package     Rootwork\Sfax
 * @copyright   Copyright (c) 2016 Rootwork InfoTech LLC (www.rootwork.it)
 * @license     MIT
 * @author      Mike Soule <mike@rootwork.it>
 * @filesource
 */

namespace Rootwork\Sfax;

use GuzzleHttp\Client as HttpClient;
use Psr\Http\Message\ResponseInterface;
use DateTime;
use DateTimeZone;
use Rootwork\Sfax\Exception\InvalidResponseException;

/**
 * Client
 *
 * @package     Rootwork\Sfax
 */
class Client
{

    /**
     * Class constants
     */
    const DIRECTION_INBOUND     = 'Inbound';
    const DIRECTION_OUTBOUND    = 'Outbound';
    const FORMAT_PDF            = 'Pdf';
    const FORMAT_TIF            = 'Tif';

    /**
     * URI for Sfax service.
     *
     * @var string
     */
    protected $uri;

    /**
     * @var string
     */
    protected $username;

    /**
     * @var string
     */
    protected $apiKey;

    /**
     * @var string
     */
    protected $encryptionKey;

    /**
     * @var string
     */
    protected $iv;

    /**
     * Security context: usually empty string.
     *
     * @var string
     */
    protected $securityContext = '';

    /**
     * Token Client: usually empty string.
     *
     * @var string
     */
    protected $tokenClient = '';

    /**
     * Open SSL encryption method to use.
     *
     * @var string
     */
    protected $encryptionMethod = 'aes-256-cbc';

    /**
     * HTTP client instance.
     *
     * @var HttpClient
     */
    protected $httpClient;

    /**
     * Client constructor.
     *
     * @param string $uri
     * @param string $username
     * @param string $apiKey
     * @param string $encryptionKey
     * @param string $iv
     * @param string $securityContext
     * @param string $tokenClient
     */
    public function __construct(
        $uri,
        $username,
        $apiKey,
        $encryptionKey,
        $iv,
        $securityContext = '',
        $tokenClient = ''
    ) {
        $this->uri              = $uri;
        $this->username         = $username;
        $this->apiKey           = $apiKey;
        $this->encryptionKey    = $encryptionKey;
        $this->iv               = $iv;
        $this->securityContext  = $securityContext;
        $this->tokenClient      = $tokenClient;
    }

    /**
     * Send a file as a fax.
     *
     * @param string    $name
     * @param string    $number
     * @param string    $filePath
     * @param array     $barcode
     * @param array     $options
     *
     * @return object
     * @throws InvalidResponseException
     */
    public function sendFax($name, $number, $filePath, array $barcode = [], array $options = [])
    {
        $params = [
            'RecipientName' => $name,
            'RecipientFax'  => $number,
        ];

        if (count($barcode)) {
            $params['BarcodeOption'] = $this->getApiParamString($barcode);
        }

        if (count($options)) {
            $params['OptionalParams'] = $this->getApiParamString($options);
        }

        $uri        = $this->getRequestUri('SendFax', $params);
        $httpClient = $this->getHttpClient();
        $response   = $httpClient->request('POST', $uri, [
            'multipart' => [
                [
                    'name'      => 'file',
                    'filename'  => basename($filePath),
                    'contents'  => fopen($filePath, 'r')
                ]
            ]
        ]);

        return $this->getJsonResponse($response);
    }

    /**
     * Send fax from URL.
     *
     * @param string    $name
     * @param string    $number
     * @param string    $fileType
     * @param string    $url
     * @param array     $barcode
     * @param array     $options
     *
     * @return object
     */
    public function sendFaxFromUrl($name, $number, $fileType, $url, array $barcode = [], array $options = [])
    {
        $fileType   = strtolower($fileType) !== 'pdf' ? self::FORMAT_TIF : self::FORMAT_PDF;
        $params     = [
            'RecipientName' => $name,
            'RecipientFax'  => $number,
            'FileType'      => $fileType,
            'FileDataURL'   => $url,
        ];

        if (count($barcode)) {
            $params['BarcodeOption'] = $this->getApiParamString($barcode);
        }

        if (count($options)) {
            $params['OptionalParams'] = $this->getApiParamString($options);
        }

        $uri        = $this->getRequestUri('SendFaxFromURL', $params);
        $httpClient = $this->getHttpClient();
        $response   = $httpClient->request('POST', $uri);

        return $this->getJsonResponse($response);
    }

    /**
     * Get a list of inbound faxes.
     *
     * @param integer|null  $watermarkId
     * @param string|null   $startDate
     * @param string|null   $endDate
     * @param integer|null  $maxItems
     *
     * @return object
     */
    public function receiveInboundFax($watermarkId = null, $startDate = null, $endDate = null, $maxItems = null)
    {
        return $this->receiveFax(self::DIRECTION_INBOUND, $watermarkId, $startDate, $endDate, $maxItems);
    }

    /**
     * Get a list of outbound faxes.
     *
     * @param integer|null  $watermarkId
     * @param string|null   $startDate
     * @param string|null   $endDate
     * @param integer|null  $maxItems
     *
     * @return object
     */
    public function receiveOutboundFax($watermarkId = null, $startDate = null, $endDate = null, $maxItems = null)
    {
        return $this->receiveFax(self::DIRECTION_OUTBOUND, $watermarkId, $startDate, $endDate, $maxItems);
    }

    /**
     * Get a list of inbound or outbound faxes.
     *
     * @param string        $direction Inbound or Outbound
     * @param integer|null  $watermarkId
     * @param string|null   $startDate
     * @param string|null   $endDate
     * @param integer|null  $maxItems
     *
     * @return object
     */
    protected function receiveFax(
        $direction = 'Inbound',
        $watermarkId = null,
        $startDate = null,
        $endDate = null,
        $maxItems = null
    ) {
        $params = [];

        if (null !== $watermarkId) {
            $params['WatermarkId'] = $watermarkId;
        }

        if (null !== $startDate) {
            $startDate = (new DateTime($startDate))->setTimezone(new DateTimeZone('UTC'));
            $params['StartDateUTC'] = $startDate->format('Y-m-d\TH:i:s\Z');
        }

        if (null !== $endDate) {
            $endDate = (new DateTime($endDate))->setTimezone(new DateTimeZone('UTC'));
            $params['EndDateUTC'] = $endDate->format('Y-m-d\TH:i:s\Z');
        }

        if (null !== $watermarkId) {
            $params['MaxItems'] = intval($maxItems);
        }

        $method     = strtolower($direction) !== 'outbound' ? 'ReceiveInboundFax' : 'ReceiveOutboundFax';
        $uri        = $this->getRequestUri($method, $params);
        $httpClient = $this->getHttpClient();
        $response   = $httpClient->request('GET', $uri);

        return $this->getJsonResponse($response);
    }

    /**
     * Download inbound fax as PDF.
     *
     * @param integer $faxId
     *
     * @return object|string
     * @throws InvalidResponseException
     */
    public function downloadInboundFaxAsPdf($faxId)
    {
        return $this->downloadFax($faxId, self::DIRECTION_INBOUND, self::FORMAT_PDF);
    }

    /**
     * Download inbound fax as TIFF.
     *
     * @param integer $faxId
     *
     * @return object|string
     * @throws InvalidResponseException
     */
    public function downloadInboundFaxAsTif($faxId)
    {
        return $this->downloadFax($faxId, self::DIRECTION_INBOUND, self::FORMAT_TIF);
    }

    /**
     * Download outbound fax as PDF.
     *
     * @param integer $faxId
     *
     * @return object|string
     * @throws InvalidResponseException
     */
    public function downloadOutboundFaxAsPdf($faxId)
    {
        return $this->downloadFax($faxId, self::DIRECTION_OUTBOUND, self::FORMAT_PDF);
    }

    /**
     * Download outbound fax as TIFF.
     *
     * @param integer $faxId
     *
     * @return object|string
     * @throws InvalidResponseException
     */
    public function downloadOutboundFaxAsTif($faxId)
    {
        return $this->downloadFax($faxId, self::DIRECTION_OUTBOUND, self::FORMAT_TIF);
    }

    /**
     * Download fax.
     *
     * @param string    $direction Inbound or Outbound
     * @param integer   $faxId
     * @param string    $format
     *
     * @return object|string
     * @throws InvalidResponseException
     */
    protected function downloadFax($faxId, $direction = 'Inbound', $format = 'PDF')
    {
        $direction  = strtolower($direction) !== 'outbound' ? self::DIRECTION_INBOUND : self::DIRECTION_OUTBOUND;
        $format     = strtolower($format) !== 'pdf' ? self::FORMAT_TIF : self::FORMAT_PDF;
        $method     = "Download{$direction}FaxAs{$format}";
        $uri        = $this->getRequestUri($method, ['FaxId' => $faxId]);
        $httpClient = $this->getHttpClient();
        $response   = $httpClient->request('GET', $uri);
        $contents   = $response->getBody()->getContents();

        if ($response->getStatusCode() == 200) {
            $json = json_decode($contents);

            if (null !== $json) {
                return $json;
            }

            return $contents;
        }

        throw new InvalidResponseException('The response returned was unusable.');
    }

    /**
     * Get an Sfax method request URI.
     *
     * @param string    $apiMethod
     * @param array     $params
     *
     * @return string
     */
    protected function getRequestUri($apiMethod, array $params = [])
    {
        $args   = [
            'token=' . urlencode($this->getToken()),
            'ApiKey=' . urlencode($this->apiKey),
        ];

        foreach ($params as $key => $val) {
            $args[] = "$key=" . urlencode($val);
        }

        return "{$this->uri}/$apiMethod?" . implode('&', $args);
    }

    /**
     * Process and return the response.
     *
     * @param ResponseInterface $response
     *
     * @return object
     * @throws InvalidResponseException
     */
    protected function getJsonResponse(ResponseInterface $response)
    {
        if ($response->getStatusCode() == 200) {
            $json = json_decode($response->getBody()->getContents());

            if (null !== $json) {
                return $json;
            }
        }

        throw new InvalidResponseException('The response returned was unusable.');
    }

    /**
     * Get a security token.
     *
     * @return string
     */
    protected function getToken()
    {
        $tokenData  = implode('&', [
            "Context={$this->securityContext}",
            "Username={$this->username}",
            "ApiKey={$this->apiKey}",
            "GenDT=" . $this->getTokenDate(),
        ]);

        return openssl_encrypt(
            $tokenData,
            $this->encryptionMethod,
            $this->encryptionKey,
            0,
            $this->iv
        );
    }

    /**
     * Returns the GMT date for the token.
     *
     * @return false|string
     */
    protected function getTokenDate()
    {
        return gmdate('Y-m-d\TH:i:s\Z');
    }

    /**
     * Get the HTTP client instance.
     *
     * @return HttpClient
     */
    protected function getHttpClient()
    {
        if (!$this->httpClient) {
            $this->httpClient = new HttpClient();
        }

        return $this->httpClient;
    }

    /**
     * Returns parameters compiled to a single query param.
     *
     * @param array  $params
     * @param string $delimiter
     *
     * @return string
     */
    protected function getApiParamString(array $params = [], $delimiter = ';')
    {
        $pairs = [];

        foreach ($params as $key => $val) {
            $pairs[] = "$key=$val";
        }

        return implode($delimiter, $pairs);
    }
}
