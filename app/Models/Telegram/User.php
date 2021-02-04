<?php

namespace App\Models\Telegram;

use Illuminate\Database\Eloquent\Model;

class User extends Model
{
    protected $table = 'telegram_users';

    protected $attributes = [
        'is_pending' => true,
    ];

    protected $fillable = [
        'is_pending',
        'username',
        'firstname',
        'lastname',
    ];
}
