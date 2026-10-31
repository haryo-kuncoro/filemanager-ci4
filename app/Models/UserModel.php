<?php

namespace App\Models;

use CodeIgniter\Model;

class UserModel extends Model
{
    protected $table            = 'users';
    protected $primaryKey       = 'id';
    protected $returnType       = 'array';
    protected $useAutoIncrement = true;
    protected $useTimestamps    = true;
    protected $allowedFields    = [
        'name', 'username', 'email', 'password_hash', 'role_id', 'is_active',
        'must_change_password', 'last_login_at', 'last_login_ip',
    ];

    /** Daftar pengguna + nama peran. */
    public function withRoles(): array
    {
        return $this->select('users.*, roles.label AS role_label, roles.name AS role_name')
            ->join('roles', 'roles.id = users.role_id', 'left')
            ->orderBy('users.name', 'ASC')
            ->findAll();
    }

    public function findByUsername(string $username): ?array
    {
        return $this->where('username', $username)->first();
    }

    /** Jumlah administrator aktif (peran yang memiliki izin users.manage). */
    public function countActiveAdmins(?int $exceptUserId = null): int
    {
        $b = $this->db->table('users')
            ->join('role_permissions rp', 'rp.role_id = users.role_id')
            ->join('permissions p', 'p.id = rp.permission_id')
            ->where('p.code', 'users.manage')
            ->where('users.is_active', 1);
        if ($exceptUserId !== null) {
            $b->where('users.id !=', $exceptUserId);
        }

        return $b->countAllResults();
    }
}
