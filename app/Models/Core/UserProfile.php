<?php

namespace App\Models\Core;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

// Model UserProfile - Representasi tabel user_profiles
class UserProfile extends Model
{
    use HasFactory;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'user_id',
        'employee_code',
        'name',
        'phone',
        'avatar_url',
        'department_id',
        'position',
        'badge_number',
        'join_date',
        'resign_date',
    ];

    protected $casts = [
        'join_date'   => 'date',
        'resign_date' => 'date',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }
}
