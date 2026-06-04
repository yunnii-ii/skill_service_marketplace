<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, HasRoles, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'profile_bio',
        'is_banned',
    ];
    protected $guard_name = 'api';

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
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
