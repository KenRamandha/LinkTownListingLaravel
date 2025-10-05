<?php

namespace App\Models\Menus;

use Illuminate\Database\Eloquent\Model;

class MenuItemBit extends Model
{
    public $incrementing=false; protected $keyType='string';
    protected $table='menu_item_bits';
}
