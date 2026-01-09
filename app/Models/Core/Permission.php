<?php

namespace App\Models\Core;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

// Model Permission - Representasi tabel permissions
class Permission extends Model
{
    use HasFactory;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'feature_id',
        'action_id',
        'key',
    ];

    public function feature()
    {
        return $this->belongsTo(Feature::class);
    }

    public function action()
    {
        return $this->belongsTo(Action::class);
    }

    public function roles()
    {
        return $this->belongsToMany(Role::class, 'role_permissions')
            ->withPivot('allow')
            ->withTimestamps();
    }

    public function users()
    {
        return $this->belongsToMany(User::class, 'user_permissions')
            ->withPivot('allow')
            ->withTimestamps();
    }
}
