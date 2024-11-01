<?php

declare(strict_types=1);

namespace Zenkipay\Endpoint;

use Zenkipay\HttpClient\Message\ResponseMediator;
use Zenkipay\Sdk;

final class Refunds
{
    private Sdk $sdk;

    public function __construct(Sdk $sdk)
    {
        $this->sdk = $sdk;
    }

    public function create(string $order_id, array $data): object
    {
        return ResponseMediator::getContent($this->sdk->getHttpClient()->post('/v1/pay/orders/' . $order_id . '/refunds', [], json_encode($data)));
    }
}
