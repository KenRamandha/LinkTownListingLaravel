<?php

namespace App\Models\Core;

use Illuminate\Database\Eloquent\Model;

class Feature extends Model
{
    public $incrementing=false; protected $keyType='string';
    public function module(){{ return $this->belongsTo(Module::class); }}
}
