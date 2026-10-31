<?php

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Models\ShareModel;

/**
 * API JSON: daftar, pohon, info, folder baru, ganti nama, pindah, salin, hapus (ke sampah).
 */
class Nodes extends BaseController
{
    public function list()
    {
        return $this->guard(function () {
            $files  = service('files');
            $nodes  = $files->nodes();
            $parent = $this->folderId($this->request->getGet('parent'));
            $q      = trim((string) $this->request->getGet('q'));
            $sort   = (string) ($this->request->getGet('sort') ?: 'name');
            $dir    = (string) ($this->request->getGet('dir') ?: 'asc');

            $folder = $files->needFolder($parent);
            $rows   = $nodes->children($parent, $sort, $dir, $q !== '' ? $q : null);

            $pathCache = [];
            $items     = [];
            $totalSize = 0;
            $dirs      = 0;
            $fileCount = 0;
            foreach ($rows as $r) {
                $it = $files->serialize($r);
                if ($q !== '') { // hasil pencarian menampilkan lokasi asal
                    $pid = $r['parent_id'] !== null ? (int) $r['parent_id'] : 0;
                    $pathCache[$pid] ??= $pid === 0 ? '/' : $nodes->pathString($pid);
                    $it['path'] = $pathCache[$pid];
                }
                $items[] = $it;
                if ($r['type'] === 'folder') {
                    $dirs++;
                } else {
                    $fileCount++;
                    $totalSize += (int) $r['size'];
                }
            }

            $crumbs = [['id' => null, 'name' => 'Beranda']];
            foreach ($nodes->breadcrumb($parent) as $c) {
                $crumbs[] = ['id' => (int) $c['id'], 'name' => $c['name']];
            }

            return $this->ok([
                'frozen'     => $parent !== null && $nodes->lockedAncestor($parent) !== null,
                'folder'     => $folder ? ['id' => (int) $folder['id'], 'name' => $folder['name']] : null,
                'breadcrumb' => $crumbs,
                'items'      => $items,
                'search'     => $q !== '' ? $q : null,
                'stats'      => ['folders' => $dirs, 'files' => $fileCount, 'size' => $totalSize, 'usage' => $nodes->totalSize()],
            ]);
        });
    }

    /** POST api/lock {ids, locked:bool} — hanya pemilik. */
    public function lock()
    {
        return $this->guard(function () {
            $in = $this->input();
            $n  = service('files')->setLock((array) ($in['ids'] ?? []), ! empty($in['locked']));

            return $this->ok(['changed' => $n]);
        });
    }

    public function tree()
    {
        return $this->guard(function () {
            $folders = array_map(static fn ($f) => [
                'id' => (int) $f['id'], 'parent_id' => $f['parent_id'] !== null ? (int) $f['parent_id'] : null, 'name' => $f['name'],
            ], service('files')->nodes()->allFolders());

            return $this->ok(['folders' => $folders]);
        });
    }

    public function info(int $id)
    {
        return $this->guard(function () use ($id) {
            $files = service('files');
            $nodes = $files->nodes();
            $n     = $files->need($id);

            $row = $nodes->db->table('nodes n')->select('n.*, u.name AS owner_name')
                ->join('users u', 'u.id = n.owner_id', 'left')->where('n.id', $id)->get()->getRowArray();

            $data = $files->serialize($row);
            $data['path'] = $nodes->pathString($id);
            if ($n['type'] === 'folder') {
                $data['stats'] = $nodes->folderStats($id);
            }
            $share = (new ShareModel())->findByNode($id);
            $data['share'] = $share ? ['active' => (bool) $share['is_active'], 'views' => (int) $share['view_count'], 'downloads' => (int) $share['download_count']] : null;

            return $this->ok(['item' => $data]);
        });
    }

    public function createFolder()
    {
        return $this->guard(function () {
            $in   = $this->input();
            $item = service('files')->createFolder($this->folderId($in['parent_id'] ?? null), (string) ($in['name'] ?? ''), ! empty($in['ensure']));

            return $this->ok(['item' => $item], 201);
        });
    }

    public function rename()
    {
        return $this->guard(function () {
            $in   = $this->input();
            $item = service('files')->rename((int) ($in['id'] ?? 0), (string) ($in['name'] ?? ''));

            return $this->ok(['item' => $item]);
        });
    }

    public function move()
    {
        return $this->guard(function () {
            $in = $this->input();
            $n  = service('files')->move((array) ($in['ids'] ?? []), $this->folderId($in['target_id'] ?? null));

            return $this->ok(['moved' => $n]);
        });
    }

    public function copy()
    {
        return $this->guard(function () {
            $in = $this->input();
            $n  = service('files')->copy((array) ($in['ids'] ?? []), $this->folderId($in['target_id'] ?? null));

            return $this->ok(['copied' => $n]);
        });
    }

    public function delete()
    {
        return $this->guard(function () {
            $in = $this->input();
            $n  = service('files')->trash((array) ($in['ids'] ?? []));

            return $this->ok(['deleted' => $n]);
        });
    }
}
