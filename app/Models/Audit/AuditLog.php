<?php

namespace App\Models\Audit;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    public $incrementing=false; protected $keyType='string';
    protected $table='audit_logs'; public $timestamps=false;
}
