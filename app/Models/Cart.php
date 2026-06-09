<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\User;
use App\Models\CartItem;

class Cart extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'total_items',
        'subtotal',
        'status',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
    ];

    // Cart has many items
public function items()
{
    return $this->hasMany(CartItem::class, 'cart_id');
}

    // Cart belongs to a user
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Recalculate cart totals
    public function recalculateTotals()
    {
        $this->total_items = $this->items()->sum('quantity');

        $this->subtotal = $this->items()
            ->selectRaw('SUM(quantity * price) as total')
            ->value('total') ?? 0;

        $this->save();
    }
}
