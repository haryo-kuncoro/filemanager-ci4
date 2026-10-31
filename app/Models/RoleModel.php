<?php

namespace App\Models;

use CodeIgniter\Model;

class RoleModel extends Model
{
    protected $table            = 'roles';
    protected $primaryKey       = 'id';
    protected $returnType       = 'array';
    protected $useAutoIncrement = true;
    protected $useTimestamps    = true;
    protected $allowedFields    = ['name', 'label', 'description', 'own_only', 'is_system'];

    /** Semua peran + jumlah pengguna + jumlah izin. */
    public function withCounts(): array
    {
        $roles = $this->orderBy('id', 'ASC')->findAll();
        $db    = $this->db;

        $userCounts = array_column(
            $db->table('users')->select('role_id, COUNT(*) AS c')->groupBy('role_id')->get()->getResultArray(),
            'c',
            'role_id'
        );
        $permCounts = array_column(
            $db->table('role_permissions')->select('role_id, COUNT(*) AS c')->groupBy('role_id')->get()->getResultArray(),
            'c',
            'role_id'
        );

        foreach ($roles as &$r) {
            $r['user_count'] = (int) ($userCounts[$r['id']] ?? 0);
            $r['perm_count'] = (int) ($permCounts[$r['id']] ?? 0);
        }

        return $roles;
    }

    /** @return list<string> kode izin milik peran. */
    public function permissionCodes(int $roleId): array
    {
        $rows = $this->db->table('role_permissions rp')
            ->select('p.code')
            ->join('permissions p', 'p.id = rp.permission_id')
            ->where('rp.role_id', $roleId)
            ->get()->getResultArray();

        return array_column($rows, 'code');
    }

    /** Ganti seluruh izin peran. */
    public function syncPermissions(int $roleId, array $codes): void
    {
        $ids = [];
        if ($codes !== []) {
            $ids = array_column(
                $this->db->table('permissions')->select('id')->whereIn('code', $codes)->get()->getResultArray(),
                'id'
            );
        }

        $this->db->transStart();
        $this->db->table('role_permissions')->where('role_id', $roleId)->delete();
        foreach ($ids as $pid) {
            $this->db->table('role_permissions')->insert(['role_id' => $roleId, 'permission_id' => (int) $pid]);
        }
        $this->db->transComplete();
    }

    /** Semua izin dikelompokkan: [grup => [[code,label], ...]]. */
    public function groupedPermissions(): array
    {
        $rows   = $this->db->table('permissions')->orderBy('id', 'ASC')->get()->getResultArray();
        $groups = [];
        foreach ($rows as $row) {
            $groups[$row['group_name']][] = $row;
        }

        return $groups;
    }
}
