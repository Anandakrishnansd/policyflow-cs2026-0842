<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MockInsurerPolicy extends Model
{
    use HasFactory;

    protected $fillable = [
        'provider_reference',
        'client_request_id',
        'agent_id',
        'customer_name',
        'status',
        'policy_number',
        'scenario',
        'premium_amount',
    ];

    protected $casts = [
        'premium_amount' => 'decimal:2',
    ];
}
