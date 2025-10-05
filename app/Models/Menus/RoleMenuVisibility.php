<?php

namespace App\Models\Menus;

use Illuminate\Database\Eloquent\Model;

class RoleMenuVisibility extends Model
{
    public $incrementing=false; protected $keyType='string';
    protected $table='role_menu_visibility';
}
