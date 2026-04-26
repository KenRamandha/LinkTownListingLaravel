<?php

namespace App\Models\UserProduct;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Core\User;

class MsApi extends Model
{
    protected $table = 'ms_api';

    protected $primaryKey = 'id_api';

    public $timestamps = false;

    protected $fillable = [
        'api_user',
        'api_password',
        'api_pubid',
        'api_notes',
    ];

    /**
     * Get users associated with this API config
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'ms_api', 'id_api');
    }
}
