<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Booking extends Model
{
    //
    protected $fillable=[
        'buyer_id', 'seller_id', 'service_id', 'order_note', 'due_date', 'status',
    ];

    public function service(){
        return
        $this->belongsTo(Service::class);
    }

    public function reviewe(){
        return
        $this->hasOne(Review::class);
    }

}
