<?php

namespace App\Models\Menus;

use Illuminate\Database\Eloquent\Model;

class MenuUserVisibility extends Model
{
    public $incrementing=false; protected $keyType='string';
    protected $table='menu_user_visibility';
}
