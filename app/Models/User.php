<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, HasRoles, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'phone_number',
        'company_name',
        'position',
        'address',
        'avatar',
        'cover_photo',
        'bio',
        'is_banned',
        'is_approved',
    ];
    protected $guard_name = 'api';

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'is_banned' => 'integer',
        'is_approved' => 'integer',
    ];

    public function sentMessage()
    {
        return $this->hasMany(Message::class, 'sender_id');
    }

    public function receivedMessage()
    {
        return $this->hasMany(Message::class, 'receiver_id');
    }
}
