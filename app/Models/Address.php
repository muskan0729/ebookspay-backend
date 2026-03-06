<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Address extends Model
{
  protected $fillable = [
    'user_id',
    'label',
    'full_name',
    'phone',
    'address_line1',
    'address_line2',
    'landmark',
    'city',
    'state',
    'country',
    'pincode',
    'address_type',
    'is_default',
  ];

  protected $casts = [
    'is_default' => 'boolean',
  ];

  public function user()
  {
    return $this->belongsTo(User::class);
  }
}