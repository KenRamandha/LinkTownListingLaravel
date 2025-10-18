<?php

namespace App\Models\Menus;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RoleMenuToken extends Model
{
    use HasFactory;

    public $incrementing = false;
    protected $keyType = 'string';
    protected $table = 'role_menu_tokens';

    protected $fillable = [
        'token',
        'token_len',
        'version',
        'generated_at',
        'role_id',
        'menu_id',
    ];

    protected $casts = [
        'generated_at' => 'datetime',
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
