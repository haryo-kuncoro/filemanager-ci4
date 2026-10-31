<?php

namespace App\Controllers\Api;

use App\Controllers\BaseController;

class Trash extends BaseController
{
    public function index()
    {
        return $this->guard(function () {
            $files = service('files');
            $items = [];
            foreach ($files->trashList() as $r) {
                $it                    = $files->serialize($r);
                $it['deleted_at']      = date('c', strtotime($r['deleted_at']));
                $it['deleted_by_name'] = $r['deleted_by_name'];
                $it['editable']        = true;
                if ($r['type'] === 'folder') {
                    $it['count'] = count($files->nodes()->descendants((int) $r['id'], true));
                }
                $items[] = $it;
            }

            return $this->ok(['items' => $items, 'retention_days' => config('Filemanager')->trashRetentionDays]);
        });
    }

    public function restore()
    {
        return $this->guard(function () {
            $in = $this->input();

            return $this->ok(['restored' => service('files')->restore((array) ($in['ids'] ?? []))]);
        });
    }

    public function purge()
    {
        return $this->guard(function () {
            $in = $this->input();

            return $this->ok(['purged' => service('files')->purge((array) ($in['ids'] ?? []))]);
        });
    }

    public function empty()
    {
        return $this->guard(fn () => $this->ok(['purged' => service('files')->emptyTrash()]));
    }
}
