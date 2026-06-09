<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'cart_id',
        'phone_number',
        'address',
        'pincode',
        'bill_amount',
        'order_no',
        'status',
        'payment_response',
        'transaction_id',
        'txn_token',
        'txn_id',
        'bank_txn_id',
        'payment_mode',
        'txn_date',
        'gateway',
        'payment_status',
        'status_response',

    ];

    // Relationships
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function cart()
    {
        return $this->belongsTo(Cart::class);
    }
}

