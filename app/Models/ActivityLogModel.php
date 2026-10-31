<?php

namespace App\Models;

use CodeIgniter\Model;

class ActivityLogModel extends Model
{
    protected $table         = 'activity_logs';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = false;
    protected $allowedFields = [
        'user_id', 'username', 'action', 'node_id', 'target', 'details', 'ip', 'user_agent', 'created_at',
    ];

    /**
     * Catat aktivitas. Kegagalan pencatatan tidak boleh menghentikan permintaan utama.
     *
     * @param array|string|null $details
     */
    public static function record(string $action, ?array $actor = null, ?int $nodeId = null, ?string $target = null, $details = null): void
    {
        try {
            $request = service('request');
            $agent   = method_exists($request, 'getUserAgent') ? (string) $request->getUserAgent() : '';

            (new self())->insert([
                'user_id'    => $actor['id'] ?? null,
                'username'   => isset($actor['username']) ? mb_substr((string) $actor['username'], 0, 60) : null,
                'action'     => $action,
                'node_id'    => $nodeId,
                'target'     => $target !== null ? mb_substr($target, 0, 255) : null,
                'details'    => is_array($details) ? json_encode($details, JSON_UNESCAPED_UNICODE) : $details,
                'ip'         => method_exists($request, 'getIPAddress') ? $request->getIPAddress() : null,
                'user_agent' => mb_substr($agent, 0, 255),
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $e) {
            log_message('error', 'Gagal mencatat aktivitas: ' . $e->getMessage());
        }
    }
}
