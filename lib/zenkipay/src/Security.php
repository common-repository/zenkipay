<?php

declare(strict_types=1);

namespace Zenkipay;

use Exception;

final class Security
{
    private string $rsa_private_key;

    public function __construct(string $plain_rsa_private_key)
    {
        if (!$this->validateRSAPrivateKey($plain_rsa_private_key)) {
            throw new Exception('Invalid RSA private key has been provided');
        }

        $this->rsa_private_key = $plain_rsa_private_key;
    }

    /**
     * Checks if the plain RSA private key is valid
     *
     * @param string $plain_rsa_private_key Plain RSA private key
     *
     * @return boolean
     */
    private function validateRSAPrivateKey(string $plain_rsa_private_key): bool
    {
        if (empty($plain_rsa_private_key)) {
            return false;
        }

        $private_key = openssl_pkey_get_private($plain_rsa_private_key);
        if (!is_resource($private_key) && !is_object($private_key)) {
            return false;
        }

        $public_key = openssl_pkey_get_details($private_key);
        if (is_array($public_key) && isset($public_key['key'])) {
            return true;
        }

        return false;
    }

    /**
     * Generates payload signature using the RSA private key
     *
     * @param string $payload Purchase data
     *
     * @return string
     */
    public function generateSignature(string $payload): string
    {
        $rsa_private_key = openssl_pkey_get_private($this->rsa_private_key);
        openssl_sign($payload, $signature, $rsa_private_key, 'RSA-SHA256');
        return base64_encode($signature);
    }

    /**
     * Decrypt message with RSA private key
     *
     * @param  base64_encoded string holds the encrypted message.
     * @param  integer $chunk_size Chunking by bytes to feed to the decryptor algorithm (512).
     *
     * @return string decrypted message.
     */
    public function decryptWebhookPayload(string $encrypted_msg): string
    {
        $ppk = openssl_pkey_get_private($this->rsa_private_key);
        $encrypted_msg = base64_decode($encrypted_msg);

        // Decrypt the data in the small chunks
        $a_key = openssl_pkey_get_details($ppk);
        $chunk_size = ceil($a_key['bits'] / 8);

        $offset = 0;
        $decrypted = '';

        while ($offset < strlen($encrypted_msg)) {
            $decrypted_chunk = '';
            $chunk = substr($encrypted_msg, $offset, (int) $chunk_size);

            if (openssl_private_decrypt($chunk, $decrypted_chunk, $ppk)) {
                $decrypted .= $decrypted_chunk;
            } else {
                throw new Exception('Problem decrypting the message');
            }
            $offset += $chunk_size;
        }
        return $decrypted;
    }
}
