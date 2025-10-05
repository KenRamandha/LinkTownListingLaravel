<?php

namespace App\Models\Menus;

use Illuminate\Database\Eloquent\Model;

class RoleMenuToken extends Model
{
    public $incrementing=false; protected $keyType='string';
    protected $table='role_menu_tokens';
}
