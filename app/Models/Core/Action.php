<?php

namespace App\Models\Core;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Action extends Model
{
    use HasFactory;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'module_id',
        'key',
        'name',
    ];

    public function module()
    {
        return $this->belongsTo(Module::class);
    }

    public function permissions()
    {
        return $this->hasMany(Permission::class);
    }
}
