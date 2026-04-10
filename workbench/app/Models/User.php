<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use OpenSaas\SubscriptionsForLaravel\Concerns\HasSubscriptions;

class User extends Authenticatable
{
    use HasFactory, HasSubscriptions;

    protected $fillable = ['name', 'email', 'password'];

    protected $hidden = ['password'];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
        ];
    }
}
