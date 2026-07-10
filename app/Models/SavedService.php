<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SavedService extends Model
{
    protected $fillable = [
        'buyer_id',
        'service_id',
    ];

    public function buyer()
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    public function service()
    {
        return $this->belongsTo(Service::class);
    }
}
