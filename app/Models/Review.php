<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Review extends Model
{
    //
    protected $fillable = ['booking_id', 'buyer_id', 'seller_id', 'rating', 'comment'];

    public function buyer(){
        return
        $this->belongsTo(User::class, 'buyer_id');
    }
}

