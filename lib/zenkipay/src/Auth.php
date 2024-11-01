<?php

declare(strict_types=1);

namespace Zenkipay;

use Exception;

final class Auth
{
    public const API_URL = 'https://api.zenki.fi';

    /**
     * Get Zenkipay's access token
     *
     * @return array
     */
    public static function getAccessToken(string $client_id, string $client_secret): array
    {
        $url = self::API_URL . '/v1/oauth/tokens';
        $credentials = ['clientId' => $client_id, 'clientSecret' => $client_secret, 'grantType' => 'client_credentials'];
        $headers = ['Accept: application/json', 'Content-Type: application/json'];
        $agent = 'Zenkipay-PHP/1.0';

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true, // return the transfer as a string of the return value
            CURLOPT_TIMEOUT => 30, // The maximum number of seconds to allow cURL functions to execute.
            CURLOPT_USERAGENT => $agent,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => json_encode($credentials), // Data that will send
        ]);

        $result = curl_exec($ch);
        if ($result === false) {
            throw new Exception('Cannot get the access token');
        }

        curl_close($ch);
        return json_decode($result, true);
    }
}
