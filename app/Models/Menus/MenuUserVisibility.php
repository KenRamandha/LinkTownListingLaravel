<?php

namespace App\Models\Menus;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

// Model MenuUserVisibility - Representasi tabel menu_user_visibility
class MenuUserVisibility extends Model
{
    use HasFactory;

    public $incrementing = false;
    protected $keyType = 'string';
    protected $table = 'menu_user_visibility';

    protected $fillable = [
        'id',
        'menu_item_id',
        'user_id',
        'is_visible',
    ];

    protected $casts = [
        'is_visible' => 'boolean',
    ];

    public function menuItem()
    {
        return $this->belongsTo(MenuItem::class);
    }

    public function user()
    {
        return $this->belongsTo(\App\Models\Core\User::class);
    }
}
