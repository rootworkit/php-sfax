<?php
/**
 * ClientTest
 *
 * @package     Rootwork\Test\Sfax
 * @copyright   Copyright (c) 2016 Rootwork InfoTech LLC (www.rootwork.it)
 * @license     MIT
 * @author      Mike Soule <mike@rootwork.it>
 * @filesource
 */

namespace Rootwork\Test\Sfax;

use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Rootwork\Sfax\Client;
use Rootwork\PHPUnit\Helper\Accessor;
use Rootwork\Sfax\Exception\InvalidResponseException;

/**
 * ClientTest
 *
 * @package     Rootwork\Test\Sfax
 */
class ClientTest extends TestCase
{
    use Accessor;

    /**
     * Subject under test.
     *
     * @var Client
     */
    protected $sut;

    /** @var string */
    protected $uri = 'https://www.example.com/api';

    /** @var string */
    protected $username = 'sfaxapiuser';

    /** @var string */
    protected $apiKey = '7333CD865265DCD4005D09B4E4E85CD7';

    /** @var string */
    protected $encKey = 'NO^MbtIFtW*UIp4M(dpi+G/AB4hQiAmY';

    /** @var string */
    protected $iv = '3Eug*ZQbkOqIJzu2';

    /**
     * Token generated with $this->tokenDate.
     *
     * @var string
     */
    protected $expectedToken;

    /**
     * GMT date for token generation.
     *
     * @var string
     */
    protected $tokenDate = '2016-01-01T12:00:00Z';

    /**
     * Setup the test.
     */
    public function setUp(): void
    {
        $this->sut  = $this->getMockBuilder(Client::class)
            ->onlyMethods(['getTokenDate'])
            ->setConstructorArgs([
                $this->uri,
                $this->username,
                $this->apiKey,
                $this->encKey,
                $this->iv
            ])->getMock();

        $this->sut->expects($this->any())->method('getTokenDate')
            ->willReturn($this->tokenDate);

        $this->expectedToken = implode('', [
            'NbbeCkf3RIdSfOgRvuFUxr1ge8f23EYdj644ri',
            'LVpPbX9ILo6fCU0XrzmUdUonLBmAe3DLk5ICsI',
            '3B8jsRt8xG3LRl+uUp9ZieGvyBXUjOz/DAYmSl',
            'SamEUsSVo+zjO72nXBgvsMnzpxpKNY2Cxjrg==',
        ]);
    }

    /**
     * Test sending a fax.
     */
    public function testSendFax()
    {
        $name       = 'Test Recipient';
        $number     = '19999999999';
        $filePath   = __DIR__ . '/resources/testfax.pdf';
        $barcode    = [
            'BarcodeData'   => 12345,
            'BarcodeX'      => 100,
            'BarcodeY'      => 100,
            'BarcodePage'   => 1,
            'BarcodeScale'  => 3,
        ];
        $options    = [
            'CoverPageName'         => 'Default',
            'CoverPageSubject'      => 'Test',
            'CoverPageReference'    => 'Test1234',
            'TrackingCode'          => 1234,
        ];
        $uri        = "{$this->uri}/SendFax?token=" . urlencode($this->expectedToken);
        $uri       .= '&ApiKey=' . urlencode($this->apiKey);
        $uri       .= '&RecipientName=' . urlencode($name);
        $uri       .= '&RecipientFax=' . urlencode($number);

        $barcodeParams = [];
        foreach ($barcode as $key => $val) {
            $barcodeParams[] = "$key=$val";
        }

        $uri .= '&BarcodeOption=' . urlencode(implode(';', $barcodeParams));

        $optionParams = [];
        foreach ($options as $key => $val) {
            $optionParams[] = "$key=$val";
        }

        $uri .= '&OptionalParams=' . urlencode(implode(';', $optionParams));

        $httpClient = $this->createMock('GuzzleHttp\Client');
        $expected   = (object) [
            'SendFaxQueueId'    => 'C3A81B76E088270C55BCFB7ABC158822',
            'isSuccess'         => true,
            'message'           => 'Fax is received and being processed',
        ];
        $response   = new Response(200, [], json_encode($expected));

        $httpClient->expects($this->once())->method('request')->with(
            'POST', $uri, $this->callback(function($data) {
                if (isset($data['multipart'])) {
                    return is_resource($data['multipart'][0]['contents']);
                }
                return false;
            })
        )->willReturn($response);

        $this->setPropertyValue($this->sut, 'httpClient', $httpClient);

        $actual = $this->sut->sendFax($name, $number, $filePath, $barcode, $options);

        $this->assertEquals($expected, $actual);
    }

    /**
     * Test sending a fax from a URL.
     */
    public function testSendFaxFromUrl()
    {
        $name       = 'Test Recipient';
        $number     = '19999999999';
        $fileType   = Client::FORMAT_PDF;
        $fileUrl    = 'http://www.example.com/test.pdf';
        $barcode    = [
            'BarcodeData'   => 12345,
            'BarcodeX'      => 100,
            'BarcodeY'      => 100,
            'BarcodePage'   => 1,
            'BarcodeScale'  => 3,
        ];
        $options    = [
            'CoverPageName'         => 'Default',
            'CoverPageSubject'      => 'Test',
            'CoverPageReference'    => 'Test1234',
            'TrackingCode'          => 1234,
        ];
        $uri        = "{$this->uri}/SendFaxFromURL?token=" . urlencode($this->expectedToken);
        $uri       .= '&ApiKey=' . urlencode($this->apiKey);
        $uri       .= '&RecipientName=' . urlencode($name);
        $uri       .= '&RecipientFax=' . urlencode($number);
        $uri       .= '&FileType=' . urlencode($fileType);
        $uri       .= '&FileDataURL=' . urlencode($fileUrl);

        $barcodeParams = [];
        foreach ($barcode as $key => $val) {
            $barcodeParams[] = "$key=$val";
        }

        $uri .= '&BarcodeOption=' . urlencode(implode(';', $barcodeParams));

        $optionParams = [];
        foreach ($options as $key => $val) {
            $optionParams[] = "$key=$val";
        }

        $uri .= '&OptionalParams=' . urlencode(implode(';', $optionParams));

        $httpClient = $this->createMock('GuzzleHttp\Client');
        $expected   = (object) [
            'SendFaxQueueId'    => 'C3A81B76E088270C55BCFB7ABC158822',
            'isSuccess'         => true,
            'message'           => 'Fax is received and being processed',
        ];
        $response   = new Response(200, [], json_encode($expected));

        $httpClient->expects($this->once())->method('request')
            ->with('POST', $uri)->willReturn($response);

        $this->setPropertyValue($this->sut, 'httpClient', $httpClient);

        $actual = $this->sut->sendFaxFromUrl($name, $number, $fileType, $fileUrl, $barcode, $options);

        $this->assertEquals($expected, $actual);
    }

    /**
     * Test getting inbound faxes.
     */
    public function testReceiveInboundFax()
    {
        $watermarkId    = 12345678;
        $startDate      = '2016-01-01T12:00:00Z';
        $endDate        = '2016-02-01T12:00:00Z';
        $maxItems       = 10;
        $uri        = "{$this->uri}/ReceiveInboundFax?token=" . urlencode($this->expectedToken);
        $uri       .= '&ApiKey=' . urlencode($this->apiKey);
        $uri       .= '&WatermarkId=' . urlencode($watermarkId);
        $uri       .= '&StartDateUTC=' . urlencode($startDate);
        $uri       .= '&EndDateUTC=' . urlencode($endDate);
        $uri       .= '&MaxItems=' . urlencode($maxItems);

        $httpClient = $this->createMock('GuzzleHttp\Client');
        $expected   = (object) [
            'InboundFaxItems'   => [
                (object) [
                    'FaxId'         => 10000001,
                    'Pages'         => 1,
                    'ToFaxNumber'   => '19999999999',
                    'FromFaxNumber' => '19999999999',
                    'FromCSID'      => '9999999999',
                    'FaxDateUtc'    => '01/01/2016 4:04:13 PM',
                    'FaxSuccess'    => 1,
                    'Barcodes'      => (object) [
                        'FirstBarcodePage'          => 0,
                        'TotalPagesWithBarcodes'    => 0,
                        'PagesWithBarcodes'         => [],
                        'BarcodeItems'              => [],
                    ],
                    'InboundFaxId'  => 10000001,
                    'FaxPages'      => 1,
                    'FaxDateIso'    => '2016-01-01T16:04:13Z',
                    'WatermarkId'   => 20000001,
                    'CreateDateIso' => '2016-01-01T16:04:13.5261200Z',
                ],
                (object) [
                    'FaxId'         => 10000002,
                    'Pages'         => 1,
                    'ToFaxNumber'   => '19999999999',
                    'FromFaxNumber' => '19999999999',
                    'FromCSID'      => '9999999999',
                    'FaxDateUtc'    => '01/10/2016 4:04:13 PM',
                    'FaxSuccess'    => 1,
                    'Barcodes'      => (object) [
                        'FirstBarcodePage'          => 0,
                        'TotalPagesWithBarcodes'    => 0,
                        'PagesWithBarcodes'         => [],
                        'BarcodeItems'              => [],
                    ],
                    'InboundFaxId'  => 10000002,
                    'FaxPages'      => 1,
                    'FaxDateIso'    => '2016-01-10T16:04:13Z',
                    'WatermarkId'   => 20000002,
                    'CreateDateIso' => '2016-01-10T16:04:13.5261200Z',
                ],
            ],
            'FaxCount'      => 2,
            'LastWatermark' => 20000002,
            'HasMoreItems'  => false,
            'isSuccess'     => true,
            'message'       => 'Success',
        ];
        $response   = new Response(200, [], json_encode($expected));

        $httpClient->expects($this->once())->method('request')
            ->with('GET', $uri)->willReturn($response);

        $this->setPropertyValue($this->sut, 'httpClient', $httpClient);

        $actual = $this->sut->receiveInboundFax($watermarkId, $startDate, $endDate, $maxItems);

        $this->assertEquals($expected, $actual);
    }

    /**
     * Test getting outbound faxes.
     */
    public function testReceiveOutboundFax()
    {
        $watermarkId    = 12345678;
        $startDate      = '2016-01-01T12:00:00Z';
        $endDate        = '2016-02-01T12:00:00Z';
        $maxItems       = 10;
        $uri        = "{$this->uri}/ReceiveOutboundFax?token=" . urlencode($this->expectedToken);
        $uri       .= '&ApiKey=' . urlencode($this->apiKey);
        $uri       .= '&WatermarkId=' . urlencode($watermarkId);
        $uri       .= '&StartDateUTC=' . urlencode($startDate);
        $uri       .= '&EndDateUTC=' . urlencode($endDate);
        $uri       .= '&MaxItems=' . urlencode($maxItems);

        $expected   = (object) [
            'OutboundFaxItems'   => [
                (object) [
                    'SendFaxQueueId'    => 'ABC123',
                    'IsSuccess'         => true,
                    'ResultCode'        => 0,
                    'ErrorCode'         => 0,
                    'ResultMessage'     => 'OK',
                    'RecipientName'     => 'Malcolm Reynolds',
                    'RecipientFax'      => '1-9999999999',
                    'TrackingCode'      => '',
                    'FaxDateUtc'        => '2016-01-02T04:44:31Z',
                    'FaxId'             => 10000001,
                    'Pages'             => 1,
                    'Attempts'          => 1,
                    'SenderFax'         => '1-9999999999',
                    'BarcodeItems'      => null,
                    'FaxSuccess'        => 1,
                    'OutboundFaxId'     => 10000001,
                    'FaxPages'          => 1,
                    'FaxDateIso'        => '2016-01-02T04:44:31Z',
                    'WatermarkId'       => 20000001,
                ],
                (object) [
                    'SendFaxQueueId'    => 'DEF456',
                    'IsSuccess'         => true,
                    'ResultCode'        => 0,
                    'ErrorCode'         => 0,
                    'ResultMessage'     => 'OK',
                    'RecipientName'     => 'Shepherd Book',
                    'RecipientFax'      => '1-9999999999',
                    'TrackingCode'      => '',
                    'FaxDateUtc'        => '2016-01-03T04:44:31Z',
                    'FaxId'             => 10000002,
                    'Pages'             => 1,
                    'Attempts'          => 1,
                    'SenderFax'         => '1-9999999999',
                    'BarcodeItems'      => null,
                    'FaxSuccess'        => 1,
                    'OutboundFaxId'     => 10000002,
                    'FaxPages'          => 1,
                    'FaxDateIso'        => '2016-01-03T04:44:31Z',
                    'WatermarkId'       => 20000002,
                ],
            ],
            'FaxCount'      => 2,
            'LastWatermark' => 20000002,
            'HasMoreItems'  => false,
            'isSuccess'     => true,
            'message'       => 'Success',
        ];
        $response   = new Response(200, [], json_encode($expected));

        $httpClient = $this->createMock('GuzzleHttp\Client');
        $httpClient->expects($this->once())->method('request')
            ->with('GET', $uri)->willReturn($response);

        $this->setPropertyValue($this->sut, 'httpClient', $httpClient);

        $actual = $this->sut->receiveOutboundFax($watermarkId, $startDate, $endDate, $maxItems);

        $this->assertEquals($expected, $actual);
    }

    /**
     * Test downloading faxes.
     *
     * @param string|object|\Exception  $expected
     * @param string                    $direction
     * @param string                    $format
     * @param integer                   $faxId
     *
     * @dataProvider provideDownloadFax
     */
    public function testDownloadFax($expected, $direction, $format, $faxId)
    {
        $uri        = "{$this->uri}/Download{$direction}FaxAs{$format}";
        $uri       .= '?token=' . urlencode($this->expectedToken);
        $uri       .= '&ApiKey=' . urlencode($this->apiKey);
        $uri       .= '&FaxId=' . urlencode($faxId);
        $method     = "download{$direction}FaxAs{$format}";

        if ($expected instanceof \Exception) {
            $this->expectException(get_class($expected));
            $this->expectExceptionMessage($expected->getMessage());

            $response = new Response(400);
        } elseif (is_object($expected)) {
            $response = new Response(200, [], json_encode($expected));
        } else {
            $response = new Response(200, [], $expected);
        }

        $httpClient = $this->createMock('GuzzleHttp\Client');
        $httpClient->expects($this->once())->method('request')
            ->with('GET', $uri)->willReturn($response);

        $this->setPropertyValue($this->sut, 'httpClient', $httpClient);

        $actual = $this->sut->$method($faxId);

        $this->assertEquals($expected, $actual);
    }

    /**
     * Provide data for testing fax downloads.
     *
     * @return array
     */
    public function provideDownloadFax()
    {
        return [
            [
                file_get_contents(__DIR__ . '/resources/testfax.pdf'),
                Client::DIRECTION_INBOUND,
                Client::FORMAT_PDF,
                10000001,
            ],
            [
                file_get_contents(__DIR__ . '/resources/testfax.tif'),
                Client::DIRECTION_INBOUND,
                Client::FORMAT_TIF,
                10000001,
            ],
            [
                file_get_contents(__DIR__ . '/resources/testfax.pdf'),
                Client::DIRECTION_OUTBOUND,
                Client::FORMAT_PDF,
                10000001,
            ],
            [
                file_get_contents(__DIR__ . '/resources/testfax.tif'),
                Client::DIRECTION_OUTBOUND,
                Client::FORMAT_TIF,
                10000001,
            ],
            [
                (object) [
                    'isSuccess' => false,
                    'message'   => 'Invalid FaxId parameter.',
                ],
                Client::DIRECTION_INBOUND,
                Client::FORMAT_PDF,
                12345678,
            ],
            [
                new InvalidResponseException('The response returned was unusable.'),
                Client::DIRECTION_INBOUND,
                Client::FORMAT_PDF,
                12345678,
            ],
        ];
    }

    /**
     * Test getting a JSON response.
     *
     * @param object|\Exception $expected
     * @param integer           $status
     * @param string            $body
     *
     * @dataProvider provideGetJsonResponse
     */
    public function testGetJsonResponse($expected, $status, $body)
    {
        if ($expected instanceof \Exception) {
            $this->expectException(get_class($expected));
            $this->expectExceptionMessage($expected->getMessage());
        }

        $response   = new Response($status, [], $body);
        $actual     = $this->invokeMethod($this->sut, 'getJsonResponse', [$response]);

        $this->assertEquals($expected, $actual);
    }

    /**
     * Provide data for testing JSON responses.
     *
     * @return array
     */
    public function provideGetJsonResponse()
    {
        return [
            [
                (object) [
                    'SendFaxQueueId'    => 'C3A81B76E088270C55BCFB7ABC158822',
                    'isSuccess'         => true,
                    'message'           => 'Fax is received and being processed',
                ],
                200,
                implode(' ', [
                    '{"SendFaxQueueId": "C3A81B76E088270C55BCFB7ABC158822", ',
                    '"isSuccess": true,',
                    '"message": "Fax is received and being processed"}',
                ]),
            ],
            [
                new InvalidResponseException('The response returned was unusable.'),
                400,
                'abc123',
            ],
        ];
    }

    /**
     * Test getting a valid API token.
     */
    public function testGetToken()
    {
        $actual = $this->invokeMethod($this->sut, 'getToken');

        $this->assertEquals($this->expectedToken, $actual);
    }

    /**
     * Test getting a token date.
     */
    public function testGetTokenDate()
    {
        $pattern    = '/\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z/';
        $sut        = new Client(
            $this->uri,
            $this->username,
            $this->apiKey,
            $this->encKey,
            $this->iv
        );
        $actual     = $this->invokeMethod($sut, 'getTokenDate');

        $this->assertEquals(1, preg_match($pattern, $actual));
    }

    /**
     * Test getting an HTTP client.
     */
    public function testGetHttpClient()
    {
        $actual = $this->invokeMethod($this->sut, 'getHttpClient');

        $this->assertInstanceOf('GuzzleHttp\Client', $actual);
    }
}
