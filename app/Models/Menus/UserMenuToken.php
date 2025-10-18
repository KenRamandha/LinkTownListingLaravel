<?php

namespace App\Models\Menus;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserMenuToken extends Model
{
    use HasFactory;

    public $incrementing = false;
    protected $keyType = 'string';
    protected $table = 'user_menu_tokens';

    protected $fillable = [
        'token',
        'token_len',
        'version',
        'generated_at',
        'user_id',
        'menu_id',
    ];

    protected $casts = [
        'generated_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(\App\Models\Core\User::class);
    }

    public function menu()
    {
        return $this->belongsTo(Menu::class);
    }
}
