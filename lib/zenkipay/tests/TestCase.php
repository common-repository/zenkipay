<?php

declare(strict_types=1);

namespace Zenkipay\Tests;

use Zenkipay\ClientBuilder;
use Zenkipay\Options;
use Zenkipay\Sdk;
use Http\Mock\Client;

abstract class TestCase extends \PHPUnit\Framework\TestCase
{
    protected Client $mockClient;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockClient = new Client();
    }

    protected function givenSdk(): Sdk
    {
        $client_id = 'xxx';
        $client_secret = 'xxx';

        return new Sdk(
            $client_id,
            $client_secret,
            new Options([
                'client_builder' => new ClientBuilder($this->mockClient),
                'uri' => 'https://jsonplaceholder.typicode.com',
            ])
        );
    }
}
