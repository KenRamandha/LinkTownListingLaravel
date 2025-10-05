<?php

namespace App\Models\Menus;

use Illuminate\Database\Eloquent\Model;

class UserMenuToken extends Model
{
    public $incrementing=false; protected $keyType='string';
    protected $table='user_menu_tokens';
}
