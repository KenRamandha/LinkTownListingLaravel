<?php

namespace App\Models\Core;

use App\Models\Menus\MenuItem;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

// Model Feature - Representasi tabel features
class Feature extends Model
{
    use HasFactory;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'module_id',
        'key',
        'name',
        'description',
    ];

    public function module()
    {
        return $this->belongsTo(Module::class);
    }

    public function permissions()
    {
        return $this->hasMany(Permission::class);
    }

    public function menuItems()
    {
        return $this->hasMany(MenuItem::class);
    }
}
