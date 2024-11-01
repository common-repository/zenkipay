<?php

declare(strict_types=1);

namespace Zenkipay\Endpoint;

use Zenkipay\HttpClient\Message\ResponseMediator;
use Zenkipay\Sdk;

final class Todos
{
    private Sdk $sdk;

    public function __construct(Sdk $sdk)
    {
        $this->sdk = $sdk;
    }

    public function all(): array
    {
        return ResponseMediator::getContent($this->sdk->getHttpClient()->get('/todos'));
    }

    public function retrieve(string $id): object
    {
        return ResponseMediator::getContent($this->sdk->getHttpClient()->get('/todos/' . $id));
    }
}
