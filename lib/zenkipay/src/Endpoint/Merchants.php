<?php

declare(strict_types=1);

namespace Zenkipay\Endpoint;

use Zenkipay\HttpClient\Message\ResponseMediator;
use Zenkipay\Sdk;

final class Merchants
{
    private Sdk $sdk;

    public function __construct(Sdk $sdk)
    {
        $this->sdk = $sdk;
    }

    public function me(): object
    {
        return ResponseMediator::getContent($this->sdk->getHttpClient()->get('/v1/pay/me'));
    }
}
