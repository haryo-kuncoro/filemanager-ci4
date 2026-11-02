<?php

namespace App\Controllers;

/**
 * Halaman 404 untuk rute yang tidak dikenal (dipasang lewat set404Override).
 */
class ErrorPage extends BaseController
{
    public function notFound()
    {
        // Permintaan API mendapat JSON, bukan halaman HTML.
        if (str_starts_with(ltrim($this->request->getPath(), '/'), 'api/')
            || str_contains(strtolower($this->request->getHeaderLine('Accept')), 'application/json')) {
            return $this->response->setStatusCode(404)->setJSON(['error' => 'Tidak ditemukan.']);
        }

        return $this->response
            ->setStatusCode(404)
            ->setBody(view('errors/not_found', ['message' => '']));
    }
}
