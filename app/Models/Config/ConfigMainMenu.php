<?php

namespace App\Models\Config;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ConfigMainMenu extends Model
{
    use HasFactory;

    protected $table = 'config_main_menu';

    protected $fillable = [
        'menu_name',
        'menu_icon',
        'menu_route',
        'menu_order',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'menu_order' => 'integer',
    ];

    public function subMenus()
    {
        return $this->hasMany(ConfigSubMenu::class, 'main_menu_id');
    }

    public function permissions()
    {
        return $this->hasMany(ConfigMainMenuPermission::class, 'main_menu_id');
    }
}
