<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EbookAccess extends Model
{
    protected $table = 'ebook_access';

    protected $fillable = [
        'user_id',
        'order_id',
        'ebook_id',
        'access_expiry_date',
        'is_active'
    ];

    public function ebook()
    {
        return $this->belongsTo(Ebook::class, 'ebook_id');
    }

    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id');
    }
}