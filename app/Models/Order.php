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
        'transaction_id', // ✅ ADD THIS
        'payment_response', // ✅ ADD THIS (optional, for debugging)
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

