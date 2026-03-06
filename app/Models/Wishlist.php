<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Wishlist extends Model
{
  protected $fillable = [
    'user_id',
    'ebook_id',
  ];

  public function user()
  {
    return $this->belongsTo(User::class);
  }

  public function ebook()
  {
    return $this->belongsTo(Ebook::class, 'ebook_id');
  }
}