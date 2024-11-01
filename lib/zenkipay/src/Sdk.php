<?php

declare(strict_types=1);

namespace Zenkipay;

use Zenkipay\Auth;
use Zenkipay\Endpoint\Todos;
use Zenkipay\Endpoint\Orders;
use Zenkipay\Endpoint\Refunds;
use Zenkipay\Endpoint\TrackingNumbers;
use Zenkipay\Endpoint\Merchants;
use Http\Client\Common\HttpMethodsClientInterface;
use Http\Client\Common\Plugin\BaseUriPlugin;
use Http\Client\Common\Plugin\HeaderDefaultsPlugin;

final class Sdk
{
    private ClientBuilder $clientBuilder;
    private string $client_id;
    private string $client_secret;

    public function __construct(string $client_id, string $client_secret, Options $options = null)
    {
        $this->client_id = $client_id;
        $this->client_secret = $client_secret;
        $options = $options ?? new Options();
        $access_token = $this->getAccessToken();

        $this->clientBuilder = $options->getClientBuilder();
        $this->clientBuilder->addPlugin(new BaseUriPlugin($options->getUri()));
        $this->clientBuilder->addPlugin(
            new HeaderDefaultsPlugin([
                'Authorization' => 'Bearer ' . $access_token,
                'User-Agent' => 'zenkipay-php',
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ])
        );
    }

    public function getHttpClient(): HttpMethodsClientInterface
    {
        return $this->clientBuilder->getHttpClient();
    }

    public function getAccessToken(): string
    {
        $access_token = Auth::getAccessToken($this->client_id, $this->client_secret);
        return $access_token['accessToken'];
    }

    public function todos(): Todos
    {
        return new Endpoint\Todos($this);
    }

    public function refunds(): Refunds
    {
        return new Endpoint\Refunds($this);
    }

    public function trackingNumbers(): TrackingNumbers
    {
        return new Endpoint\TrackingNumbers($this);
    }

    public function orders(): Orders
    {
        return new Endpoint\Orders($this);
    }

    public function merchants(): Merchants
    {
        return new Endpoint\Merchants($this);
    }
}
