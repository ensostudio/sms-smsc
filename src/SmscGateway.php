<?php

namespace EnsoStudio\Sms\Gateway;

use InvalidArgumentException;
use EnsoStudio\Sms\HttpGateway;
use EnsoStudio\Sms\ResultInterface;
use EnsoStudio\Sms\Result;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * The gateway "smsc.ru".
 *
 * @link https://smsc.ru/api/ The API documentation
 */
class SmscGateway extends HttpGateway
{
    /**
     * @var string Special API key (you can create key in your merchant profile)
     */
    protected string $apiKey;
    /**
     * @var string|null The short name of sender
     */
    protected ?string $sender = null;
    /**
     * @var bool Transliterate message?
     */
    protected bool $translit = false;
    /**
     * @var bool Replace links in message to short ones?
     */
    protected bool $tinyUrl = false;

    public function getName(): string
    {
        return 'smsc.ru';
    }

    protected function getUri(): string
    {
        return 'https://smsc.ru/sys/send.php';
    }

    /**
     * @inheritDoc
     * @throws InvalidArgumentException Message too long
     */
    protected function createHttpRequest(string $message, array $recipientPhones): RequestInterface
    {
        if (strlen($message) > 1000) {
            throw new InvalidArgumentException('Message too long (max: 1000)');
        }

        $data = [
            'apikey' => $this->apiKey,
            'phones' => implode(',', $recipientPhones),
            'mes' => $message,
            'translit' => $this->translit,
            'tinyurl' => $this->tinyUrl,
            'charset' => 'utf-8',
            'fmt' => 3, // JSON response
            'op' => 1, // Add information for each phone number to response
            'err' => 1, // Add errors by phone numbers with corresponding statuses to response
        ];
        if ($this->sender) {
            $data['sender'] = $this->sender;
        }

        $request = $this->getHttpRequestFactory()
            ->createRequest('POST', $this->getUri())
            ->withHeader('Content-Type', 'application/x-www-form-urlencoded');
        $request->getBody()->write(http_build_query($data, '', '&'));

        return $request;
    }

    /**
     * @inheritDoc
     * @return Result
     * @throws \JsonException Invalid JSON in response
     * @throws InvalidArgumentException Invalid format of response data
     */
    protected function createResult(ResponseInterface $httpResponse): ResultInterface
    {
        $response = json_decode((string) $httpResponse->getBody(), true, 10, JSON_THROW_ON_ERROR);
        if (empty($response['phones'])) {
            throw new InvalidArgumentException('Invalid format of response data');
        }

        $result = new Result();
        foreach ($response['phones'] as $data) {
            if (!empty($data['error'])) {
                $statusCode = ResultInterface::STATUS_GATEWAY_ERROR;
                if (in_array($data['error'], [1, 5, 6, 7, 8])) {
                    $statusCode = ResultInterface::STATUS_BAD_REQUEST;
                } elseif ($data['error'] == 2) {
                    $statusCode = ResultInterface::STATUS_UNAUTHORIZED;
                } elseif ($data['error'] == 3) {
                    $statusCode = ResultInterface::STATUS_PAYMENT_REQUIRED;
                } elseif (in_array($data['error'], [4, 9])) {
                    $statusCode = ResultInterface::STATUS_TOO_MANY_REQUESTS;
                }
                $result->addError($data['phone'], $statusCode);
            }
        }

        return $result;
    }
}
