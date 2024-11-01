<?php

declare(strict_types=1);

namespace Zenkipay\HttpClient\Message;

use Psr\Http\Message\ResponseInterface;

final class ResponseMediator
{
    public static function getContent(ResponseInterface $response): object
    {
        return json_decode($response->getBody()->getContents());
    }
}
