<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    protected $fillable = [
        'user_id',
        'doctor_id',
        'amount',
        'currency',
        'provider',
        'payment_account',
        'status',
        'external_reference',
        'description',
    ];
}
