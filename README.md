SMS gateway for service [smsc.ru](https://smsc.ru)

## Example

Use [GuzzleHttp](https://github.com/guzzle/psr7) to send request:

```php
$gateway = new \EnsoStudio\Sms\Gateway\SmscGateway(
    ['apiKey' => '...'],
    new \GuzzleHttp\Client(),
    new \GuzzleHttp\Psr7\HttpFactory()
);
$result = $gateway->sendSms('Test message', [\EnsoStudio\Sms\PhoneUtils::sanitizeNumber('+7 905 710-71-71')]);
if (!$result->isSuccess()) {
    // error handler for $result->getErrors()
}
```