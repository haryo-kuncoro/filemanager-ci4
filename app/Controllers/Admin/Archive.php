<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Libraries\ArchiveReader;
use App\Libraries\FileException;
use App\Models\ActivityLogModel;

/**
 * Jelajah & ekstrak isi backup TANPA memulihkan. Khusus administrator (filter `admin` pada rute):
 * seluruh item arsip terbaca — milik siapa pun, pribadi, maupun terkunci.
 */
class Archive extends BaseController
{
    private function reader(string $name): ArchiveReader
    {
        return new ArchiveReader(service('storage'), service('backup'), $name);
    }

    public function index(string $name)
    {
        try {
            $r = $this->reader($name);
            $summary = $r->summary();
        } catch (FileException $e) {
            return redirect()->to(site_url('admin/backups'))->with('error', $e->getMessage());
        }
        ActivityLogModel::record('archive_open', $this->auth->user(), null, $name);

        return view('admin/archive/index', [
            'title'      => 'Jelajahi arsip',
            'summary'    => $summary,
            'name'       => $name,
            'csrfHeader' => config('Security')->headerName,
        ]);
    }

    public function list(string $name)
    {
        return $this->guard(function () use ($name) {
            $parent = $this->folderId($this->request->getGet('parent'));
            $data   = $this->reader($name)->list($parent, (string) $this->request->getGet('q'), (bool) $this->request->getGet('trash'));

            return $this->ok($data);
        });
    }

    public function file(string $name, int $id)
    {
        try {
            $inline = (bool) $this->request->getGet('inline');
            $res    = $this->reader($name)->fileResponse($id, $inline);
            if (! $inline) {
                ActivityLogModel::record('archive_download', $this->auth->user(), null, $name, ['item' => $id]);
            }

            return $res;
        } catch (FileException $e) {
            return $this->response->setStatusCode($e->httpCode())->setBody(view('errors/message', ['message' => $e->getMessage(), 'code' => $e->httpCode()]));
        }
    }

    public function plan(string $name)
    {
        return $this->guard(function () use ($name) {
            $in = $this->input();

            return $this->ok($this->reader($name)->planSummary((array) ($in['ids'] ?? []), ! empty($in['trash'])));
        });
    }

    public function start(string $name)
    {
        return $this->guard(function () use ($name) {
            $in = $this->input();

            return $this->ok($this->reader($name)->start((array) ($in['ids'] ?? []), ! empty($in['trash']), $this->auth->user()));
        });
    }

    public function step(string $name)
    {
        return $this->guard(function () use ($name) {
            $in = $this->input();

            return $this->ok($this->reader($name)->step((string) ($in['job'] ?? ''), $this->auth->user()));
        });
    }

    public function cancel(string $name)
    {
        return $this->guard(function () use ($name) {
            $in = $this->input();
            $this->reader($name)->cancel((string) ($in['job'] ?? ''), $this->auth->user());

            return $this->ok();
        });
    }
}
