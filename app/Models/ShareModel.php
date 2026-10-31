<?php

namespace App\Models;

use CodeIgniter\Model;

class ShareModel extends Model
{
    protected $table            = 'shares';
    protected $primaryKey       = 'id';
    protected $returnType       = 'array';
    protected $useAutoIncrement = true;
    protected $useTimestamps    = true;
    protected $allowedFields    = [
        'node_id', 'token', 'password_hash', 'expires_at', 'allow_download', 'is_active',
        'view_count', 'download_count', 'created_by',
    ];

    public function findByToken(string $token): ?array
    {
        if (! preg_match('/^[a-f0-9]{32,64}$/', $token)) {
            return null;
        }

        return $this->where('token', $token)->first();
    }

    public function findByNode(int $nodeId): ?array
    {
        return $this->where('node_id', $nodeId)->first();
    }

    public static function newToken(): string
    {
        return bin2hex(random_bytes(24)); // 48 karakter hex (192 bit)
    }

    /** Daftar semua tautan + info node & pembuat, untuk halaman admin. */
    public function listAll(?int $onlyUserId = null): array
    {
        $b = $this->db->table('shares s')
            ->select('s.*, n.name AS node_name, n.type AS node_type, n.deleted_at AS node_deleted_at, u.name AS creator_name')
            ->join('nodes n', 'n.id = s.node_id', 'left')
            ->join('users u', 'u.id = s.created_by', 'left')
            ->orderBy('s.created_at', 'DESC');
        if ($onlyUserId !== null) {
            $b->where('s.created_by', $onlyUserId);
        }

        return $b->get()->getResultArray();
    }

    /** Apakah tautan kedaluwarsa? */
    public static function isExpired(array $share): bool
    {
        return ! empty($share['expires_at']) && strtotime($share['expires_at']) < time();
    }
}
