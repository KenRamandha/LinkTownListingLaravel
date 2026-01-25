<?php

namespace App\Models\Config;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Core\Company;
use App\Models\Core\Role;

class ConfigMainMenuPermission extends Model
{
    use HasFactory;

    protected $table = 'config_main_menu_permission';

    protected $fillable = [
        'user_id',
        'role_id',
        'company_ids',
        'main_menu_id',
        'can_view',
        'can_create',
        'can_update',
        'can_delete',
    ];

    protected $casts = [
        'company_ids' => 'array',
        'can_view' => 'boolean',
        'can_create' => 'boolean',
        'can_update' => 'boolean',
        'can_delete' => 'boolean',
        'main_menu_id' => 'integer',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function role()
    {
        return $this->belongsTo(Role::class, 'role_id');
    }

    public function mainMenu()
    {
        return $this->belongsTo(ConfigMainMenu::class, 'main_menu_id');
    }
}
