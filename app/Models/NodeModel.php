<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Pohon virtual file/folder. parent_id NULL = root.
 * Item yang dihapus bersifat soft-delete (deleted_at) dan dikelompokkan lewat trash_root_id.
 */
class NodeModel extends Model
{
    protected $table            = 'nodes';
    protected $primaryKey       = 'id';
    protected $returnType       = 'array';
    protected $useAutoIncrement = true;
    protected $useTimestamps    = true;
    protected $allowedFields    = [
        'parent_id', 'type', 'name', 'storage_name', 'mime', 'ext', 'size', 'owner_id',
        'deleted_at', 'deleted_by', 'trash_root_id',
    ];

    /** Node aktif (tidak di sampah) berdasarkan id. */
    public function findActive(int $id): ?array
    {
        return $this->where('id', $id)->where('deleted_at', null)->first();
    }

    /** @return list<array> anak langsung dari folder (atau root bila null). */
    public function children(?int $parentId, string $sort = 'name', string $dir = 'asc', ?string $q = null): array
    {
        $b = $this->db->table('nodes n')
            ->select('n.*, u.name AS owner_name, (SELECT COUNT(*) FROM shares s WHERE s.node_id = n.id) AS share_count', false)
            ->join('users u', 'u.id = n.owner_id', 'left')
            ->where('n.deleted_at', null);

        if ($q !== null && $q !== '') {
            // Pencarian di seluruh pohon. Karakter wildcard (% _) dari pengguna di-escape agar dicari apa adanya.
            $char    = $this->db->likeEscapeChar;
            $escaped = str_replace([$char, '%', '_'], [$char . $char, $char . '%', $char . '_'], $q);
            $b->like('n.name', $escaped, 'both', true, true);
        } else {
            $b->where('n.parent_id', $parentId);
        }

        $dir = strtolower($dir) === 'desc' ? 'DESC' : 'ASC';
        switch ($sort) {
            case 'size':
                $b->orderBy('n.size', $dir);
                break;
            case 'date':
                $b->orderBy('n.updated_at', $dir);
                break;
            case 'type':
                $b->orderBy('n.ext', $dir);
                break;
            default:
                $b->orderBy('n.name', $dir);
        }
        $b->orderBy('n.id', 'ASC');

        $rows = $b->get()->getResultArray();

        // Folder selalu di atas.
        usort($rows, static fn ($a, $c) => ($a['type'] === 'folder' ? 0 : 1) <=> ($c['type'] === 'folder' ? 0 : 1));

        return $rows;
    }

    /** Daftar semua folder aktif (untuk pohon samping). */
    public function allFolders(): array
    {
        return $this->select('id, parent_id, name')
            ->where('type', 'folder')
            ->where('deleted_at', null)
            ->orderBy('name', 'ASC')
            ->findAll();
    }

    /** Rantai induk dari root sampai node (termasuk node sendiri). */
    public function breadcrumb(?int $id): array
    {
        $chain = [];
        $guard = 0;
        while ($id !== null && $guard++ < 100) {
            $row = $this->select('id, parent_id, name, type, deleted_at')->find($id);
            if ($row === null) {
                break;
            }
            array_unshift($chain, $row);
            $id = $row['parent_id'] !== null ? (int) $row['parent_id'] : null;
        }

        return $chain;
    }

    /** Path teks "A / B / C" untuk node (tanpa node itu sendiri bila $withSelf=false). */
    public function pathString(?int $id, bool $withSelf = true): string
    {
        $chain = $this->breadcrumb($id);
        if (! $withSelf) {
            array_pop($chain);
        }

        return '/' . implode('/', array_column($chain, 'name'));
    }

    /** Apakah ada saudara aktif dengan nama yang sama (case-insensitive)? */
    public function nameExists(?int $parentId, string $name, ?int $exceptId = null): bool
    {
        $b = $this->db->table('nodes')
            ->where('parent_id', $parentId)
            ->where('deleted_at', null)
            ->where('LOWER(name)', mb_strtolower($name));
        if ($exceptId !== null) {
            $b->where('id !=', $exceptId);
        }

        return $b->countAllResults() > 0;
    }

    /** Nama unik di folder: "nama (1).ext", "nama (2).ext", ... */
    public function uniqueName(?int $parentId, string $name, bool $isFolder = false): string
    {
        if (! $this->nameExists($parentId, $name)) {
            return $name;
        }

        $ext  = '';
        $base = $name;
        if (! $isFolder && ($dot = strrpos($name, '.')) !== false && $dot > 0) {
            $ext  = substr($name, $dot);
            $base = substr($name, 0, $dot);
        }

        for ($i = 1; $i < 10000; $i++) {
            $candidate = $base . ' (' . $i . ')' . $ext;
            if (! $this->nameExists($parentId, $candidate)) {
                return $candidate;
            }
        }

        return $base . ' (' . bin2hex(random_bytes(3)) . ')' . $ext;
    }

    /**
     * Semua turunan (rekursif) dari sebuah folder, level demi level.
     *
     * @return list<array> baris node lengkap.
     */
    public function descendants(int $id, bool $includeDeleted = false): array
    {
        $all      = [];
        $frontier = [$id];
        $guard    = 0;

        while ($frontier !== [] && $guard++ < 200) {
            $b = $this->builder()->whereIn('parent_id', $frontier);
            if (! $includeDeleted) {
                $b->where('deleted_at', null);
            }
            $rows     = $b->get()->getResultArray();
            $frontier = [];
            foreach ($rows as $row) {
                $all[] = $row;
                if ($row['type'] === 'folder') {
                    $frontier[] = (int) $row['id'];
                }
            }
        }

        return $all;
    }

    /** Apakah $id sama dengan atau berada di bawah $ancestorId? */
    public function isWithin(int $id, int $ancestorId): bool
    {
        $cur   = $id;
        $guard = 0;
        while ($cur !== null && $guard++ < 200) {
            if ($cur === $ancestorId) {
                return true;
            }
            $row = $this->select('parent_id')->find($cur);
            if ($row === null) {
                return false;
            }
            $cur = $row['parent_id'] !== null ? (int) $row['parent_id'] : null;
        }

        return false;
    }

    /** Total ukuran seluruh berkas aktif. */
    public function totalSize(): int
    {
        $row = $this->db->table('nodes')->selectSum('size', 'total')
            ->where('type', 'file')->where('deleted_at', null)->get()->getRowArray();

        return (int) ($row['total'] ?? 0);
    }

    /** Ukuran total sebuah folder (rekursif) + jumlah item. */
    public function folderStats(int $id): array
    {
        $files = 0;
        $dirs  = 0;
        $size  = 0;
        foreach ($this->descendants($id) as $row) {
            if ($row['type'] === 'folder') {
                $dirs++;
            } else {
                $files++;
                $size += (int) $row['size'];
            }
        }

        return ['files' => $files, 'folders' => $dirs, 'size' => $size];
    }

    /** Item level teratas di sampah. */
    public function trashRoots(): array
    {
        return $this->db->table('nodes n')
            ->select('n.*, u.name AS deleted_by_name, o.name AS owner_name')
            ->join('users u', 'u.id = n.deleted_by', 'left')
            ->join('users o', 'o.id = n.owner_id', 'left')
            ->where('n.deleted_at IS NOT NULL', null, false)
            ->where('n.trash_root_id = n.id', null, false)
            ->orderBy('n.deleted_at', 'DESC')
            ->get()->getResultArray();
    }
}
