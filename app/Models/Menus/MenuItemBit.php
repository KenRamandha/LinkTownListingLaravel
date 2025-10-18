<?php

namespace App\Models\Menus;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MenuItemBit extends Model
{
    use HasFactory;

    public $incrementing = false;
    protected $keyType = 'string';
    protected $table = 'menu_item_bits';

    protected $fillable = [
        'id',
        'menu_item_id',
        'bit_index',
        'is_active',
    ];

    protected $casts = [
        'bit_index' => 'int',
        'is_active' => 'boolean',
    ];

    public function item()
    {
        return $this->belongsTo(MenuItem::class, 'menu_item_id');
    }
}
