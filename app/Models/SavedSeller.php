<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SavedSeller extends Model
{
    protected $fillable = [
        'buyer_id',
        'seller_id',
    ];

    public function buyer()
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    public function seller()
    {
        return $this->belongsTo(User::class, 'seller_id');
    }
}
