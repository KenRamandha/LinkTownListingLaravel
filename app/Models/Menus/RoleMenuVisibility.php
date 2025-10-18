<?php

namespace App\Models\Menus;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RoleMenuVisibility extends Model
{
    use HasFactory;

    public $incrementing = false;
    protected $keyType = 'string';
    protected $table = 'role_menu_visibility';

    protected $fillable = [
        'id',
        'role_id',
        'menu_id',
        'visibility',
    ];

    public function role()
    {
        return $this->belongsTo(\App\Models\Core\Role::class);
    }

    public function menu()
    {
        return $this->belongsTo(Menu::class);
    }
}
