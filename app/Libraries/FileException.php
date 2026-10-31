<?php

namespace App\Libraries;

/**
 * Galat operasi file yang aman ditampilkan ke pengguna.
 * Kode = status HTTP yang sesuai (400, 403, 404, 409, 413, 422, ...).
 */
class FileException extends \RuntimeException
{
    public function __construct(string $message, int $httpCode = 400)
    {
        parent::__construct($message, $httpCode);
    }

    public function httpCode(): int
    {
        return $this->getCode() ?: 400;
    }
}
