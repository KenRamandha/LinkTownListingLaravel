<?php

namespace App\Models\Config;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ConfigSubMenu extends Model
{
    use HasFactory;

    protected $table = 'config_sub_menu';

    protected $fillable = [
        'main_menu_id',
        'menu_name',
        'menu_route',
        'menu_order',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'menu_order' => 'integer',
        'main_menu_id' => 'integer',
    ];

    public function mainMenu()
    {
        return $this->belongsTo(ConfigMainMenu::class, 'main_menu_id');
    }

    public function permissions()
    {
        return $this->hasMany(ConfigSubMenuPermission::class, 'sub_menu_id');
    }
}
