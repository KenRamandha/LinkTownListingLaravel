<?php

namespace App\Models\Menus;

use App\Models\Core\Feature;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

// Model MenuItem - Representasi tabel menu_items
class MenuItem extends Model
{
    use HasFactory;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'menu_id',
        'parent_id',
        'type',
        'label',
        'icon',
        'route',
        'url_external',
        'module_id',
        'feature_id',
        'permission_key',
        'visible_if_employee',
        'platform',
        'sort_order',
        'is_divider',
        'badge_expr',
        'meta_json',
        'is_active',
    ];

    protected $casts = [
        'visible_if_employee' => 'boolean',
        'sort_order'          => 'int',
        'is_divider'          => 'boolean',
        'is_active'           => 'boolean',
    ];

    public function menu()
    {
        return $this->belongsTo(Menu::class);
    }

    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order');
    }

    public function feature()
    {
        return $this->belongsTo(Feature::class);
    }

    public function bits()
    {
        return $this->hasMany(MenuItemBit::class);
    }

    public function userVisibilities()
    {
        return $this->hasMany(MenuUserVisibility::class);
    }
}
