<?php

namespace App\Models\Core;

use App\Models\Menus\Menu;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Company extends Model
{
    use HasFactory;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'code',
        'name',
        'phone',
        'timezone',
    ];

    public function modules()
    {
        return $this->hasMany(Module::class);
    }

    public function departments()
    {
        return $this->hasMany(Department::class);
    }

    public function roles()
    {
        return $this->hasMany(Role::class);
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function menus()
    {
        return $this->hasMany(Menu::class);
    }
}
