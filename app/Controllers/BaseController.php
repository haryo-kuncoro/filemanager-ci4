<?php

namespace App\Controllers;

use App\Libraries\FileException;
use CodeIgniter\Controller;
use CodeIgniter\HTTP\CLIRequest;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Psr\Log\LoggerInterface;

/**
 * Controller dasar. Semua controller aplikasi mewarisi kelas ini.
 */
abstract class BaseController extends Controller
{
    /** @var CLIRequest|IncomingRequest */
    protected $request;

    /** Helper yang dimuat otomatis. */
    protected $helpers = ['filemanager', 'form', 'url'];

    protected \App\Libraries\Auth $auth;

    public function initController(RequestInterface $request, ResponseInterface $response, LoggerInterface $logger)
    {
        parent::initController($request, $response, $logger);

        $this->auth = service('auth');
    }

    /** Balasan JSON seragam: {ok:true,...} / {ok:false,message}. */
    protected function ok(array $data = [], int $status = 200): ResponseInterface
    {
        return $this->response->setStatusCode($status)->setJSON(['ok' => true] + $data);
    }

    protected function fail(string $message, int $status = 400, array $extra = []): ResponseInterface
    {
        return $this->response->setStatusCode($status)->setJSON(['ok' => false, 'message' => $message] + $extra);
    }

    /** Jalankan aksi API; FileException menjadi balasan JSON yang rapi. */
    protected function guard(callable $fn): ResponseInterface
    {
        try {
            return $fn();
        } catch (FileException $e) {
            return $this->fail($e->getMessage(), $e->httpCode());
        } catch (\Throwable $e) {
            if (self::isSchemaError($e)) {
                log_message('error', 'Skema database usang: ' . $e->getMessage());

                return $this->fail('Struktur database belum diperbarui. Administrator: buka Administrasi → Pembaruan Database lalu jalankan migrasi.', 500);
            }
            log_message('error', '{class}: {msg} @ {file}:{line}', [
                'class' => $e::class, 'msg' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine(),
            ]);

            return $this->fail(ENVIRONMENT === 'production' ? 'Terjadi kesalahan pada server.' : $e->getMessage(), 500);
        }
    }

    private static function isSchemaError(\Throwable $e): bool
    {
        return (bool) preg_match('/unknown column|no such column|undefined column|column .* does not exist|no such table|doesn\'t exist/i', $e->getMessage());
    }

    /** Ambil body JSON (atau POST form) sebagai array. */
    protected function input(): array
    {
        $json = $this->request->getJSON(true);
        if (is_array($json)) {
            return $json;
        }

        return $this->request->getPost() ?: [];
    }

    /** id folder dari input: 0/''/null -> null (root). */
    protected function folderId($value): ?int
    {
        $i = (int) $value;

        return $i > 0 ? $i : null;
    }
}
