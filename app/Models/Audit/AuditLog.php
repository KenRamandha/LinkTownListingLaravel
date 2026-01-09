<?php

namespace App\Models\Audit;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

// Model AuditLog - Representasi tabel audit_logs
class AuditLog extends Model
{
    use HasFactory;

    public $incrementing = false;
    protected $keyType = 'string';
    protected $table = 'audit_logs';
    public $timestamps = false;
    protected $guarded = [];

    public function user()
    {
        return $this->belongsTo(\App\Models\Core\User::class);
    }
}
