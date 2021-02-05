<?php

namespace App\Models\Telegram;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Document extends Model
{
    use SoftDeletes;

    protected $table = 'telegram_documents';

    protected $casts = [
        'telegram_user_id' => 'integer',
        'chat_id' => 'integer',
        'message_id' => 'integer',
        'size' => 'integer',
    ];

    protected $fillable = [
        'telegram_user_id',
        'chat_id',
        'message_id',
        'file_id',
        'file_unique_id',
        'filename',
        'mime',
        'size',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'telegram_user_id');
    }
}
