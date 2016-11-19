# php-sfax
PHP client for Scrypt's Sfax service.

## Installation

```bash
composer require rootwork/php-sfax
```

## Usage Examples

### Create the Client Instance

```php
<?php
$sfax = new Rootwork\Sfax\Client(
    'https://api.sfaxme.com/api',
    'YOURUSERNAME',
    'YOURAPIKEY',
    'YOURENCRYPTIONKEY',
    'YOURIV'
);
```

### Send a Fax from a PDF or TIF File

```php
<?php
$result = $sfax->sendFax(
    'Malcolm Reynolds',
    '19999999999',
    '/path/to/file.pdf'
);

if ($result->isSuccess) {
    $queueId = $result->SendFaxQueueId;
}
```

### Send a Fax from a URL

```php
<?php
$result = $sfax->sendFaxFromUrl(
    'Malcolm Reynolds', 
    '19999999999',
    Rootwork\Sfax\Client::FORMAT_TIF,
    'https://www.yoursite.com/getFile?token=ABC123&file=12345678.tif'
);
```

### Download Inbound Faxes

```php
<?php
$result = $sfax->receiveInboundFax();

foreach ($result->InboundFaxItems as $fax) {
    $fileData = $sfax->downloadInboundFaxAsPdf($fax->FaxId);
    file_put_contents("/faxes/$fax->FaxId.pdf", $fileData);
}
```