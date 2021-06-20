<?php

namespace App\Models\Ovh;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Extension extends Model
{
    use HasFactory;

    protected $table = 'ovh_extensions';

    protected $attributes = [
        'options' => '{}',
    ];

    protected $casts = [
        'register' => 'decimal:2',
        'renew' => 'decimal:2',
        'transfer' => 'decimal:2',
        'restore' => 'decimal:2',
        'options' => 'array',
    ];

    protected $fillable = [
        'tld',
        'register',
        'renew',
        'transfer',
        'restore',
        'redemption',
        'options',
    ];

    public function scopeLatestUpdate(Builder $query)
    {
        if (!in_array('*', $query->getQuery()->columns ?? [])) {
            $query->select();
        }

        return $query->addSelect(DB::raw('max(created_at) created_at'))
                     ->groupBy('tld');
    }
}
