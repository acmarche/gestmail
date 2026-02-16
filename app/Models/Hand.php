<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Hand extends Model
{
    /** @use HasFactory<\Database\Factories\CitoyenFactory> */
    use HasFactory;

    protected $fillable = [
        'uid',
        'email',
        'password',
    ];
}
