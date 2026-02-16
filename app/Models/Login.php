<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Login extends Model
{
    protected $table = 'login';

    public $timestamps = false;

    protected $fillable = [
        'username',
        'date_connect',
        'protocol',
        'secure',
        'port',
    ];

    protected function casts(): array
    {
        return [
            'date_connect' => 'date',
            'secure' => 'integer',
            'port' => 'integer',
        ];
    }
}
