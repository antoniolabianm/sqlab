<?php

namespace mod_sqlab;

defined('MOODLE_INTERNAL') || die();

class encoder
{
    private static $key = 'aN67haft53jkas4dH8L';

    /**
     * Encrypts data using AES-256-CBC encryption.
     *
     * @param string $data The plaintext data to encrypt.
     * @return string The encrypted data encoded in Base64.
     * @throws \InvalidArgumentException If the input data is empty.
     * @throws \RuntimeException If encryption fails.
     */
    public static function encrypt($data)
    {
        if (empty($data)) {
            throw new \InvalidArgumentException("Data to encrypt cannot be empty.");
        }

        // Generate a random initialization vector (IV).
        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length('aes-256-cbc'));

        // Encrypt the data using OpenSSL.
        $encrypted = openssl_encrypt($data, 'aes-256-cbc', self::$key, 0, $iv);
        if ($encrypted === false) {
            throw new \RuntimeException("Encryption failed.");
        }

        // Return the encrypted data with IV, base64 encoded.
        return base64_encode($encrypted . '::' . $iv);
    }

    /**
     * Decrypts data encoded in Base64 and encrypted using AES-256-CBC.
     *
     * @param string $data The Base64-encoded encrypted data.
     * @return string The decrypted plaintext data.
     * @throws \InvalidArgumentException If the input data is empty or improperly formatted.
     * @throws \RuntimeException If decryption fails.
     */
    public static function decrypt($data)
    {
        if (empty($data) || count(explode('::', base64_decode($data), 2)) !== 2) {
            throw new \InvalidArgumentException("Invalid input data format for decryption.");
        }

        // Split the encrypted data and IV.
        list($encrypted_data, $iv) = explode('::', base64_decode($data), 2);

        // Decrypt the data using OpenSSL.
        $decrypted = openssl_decrypt($encrypted_data, 'aes-256-cbc', self::$key, 0, $iv);
        if ($decrypted === false) {
            throw new \RuntimeException("Decryption failed.");
        }

        return $decrypted;
    }
}
