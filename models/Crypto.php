<?php

class Crypto {
  private const CIPHER = 'aes-256-gcm';

  private static function getKey() {
    $raw = trim((string) ($_ENV['ENCRYPTION_KEY'] ?? ''));
    if ($raw === '') {
      throw new RuntimeException('Missing ENCRYPTION_KEY');
    }

    if (preg_match('/^[a-fA-F0-9]{64}$/', $raw) === 1) {
      $key = hex2bin($raw);
    } else {
      $key = $raw;
    }

    if (!is_string($key) || strlen($key) !== 32) {
      throw new RuntimeException('ENCRYPTION_KEY must be 32 bytes (or 64 hex chars)');
    }

    return $key;
  }

  public static function encryptString($plainText) {
    $iv = random_bytes(12);
    $tag = '';
    $cipherText = openssl_encrypt(
      (string) $plainText,
      self::CIPHER,
      self::getKey(),
      OPENSSL_RAW_DATA,
      $iv,
      $tag
    );

    if ($cipherText === false || $tag === '') {
      throw new RuntimeException('Encryption failed');
    }

    return [
      'ciphertext' => base64_encode($cipherText),
      'iv' => base64_encode($iv),
      'tag' => base64_encode($tag),
    ];
  }

  public static function decryptString($cipherTextB64, $ivB64, $tagB64) {
    $cipherText = base64_decode((string) $cipherTextB64, true);
    $iv = base64_decode((string) $ivB64, true);
    $tag = base64_decode((string) $tagB64, true);

    if ($cipherText === false || $iv === false || $tag === false) {
      throw new RuntimeException('Invalid encrypted payload format');
    }

    $plainText = openssl_decrypt(
      $cipherText,
      self::CIPHER,
      self::getKey(),
      OPENSSL_RAW_DATA,
      $iv,
      $tag
    );

    if ($plainText === false) {
      throw new RuntimeException('Decryption failed');
    }

    return $plainText;
  }
}

