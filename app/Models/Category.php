<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    protected $fillable = ['name','slug'];

    public function ebooks()
    {
        return $this->belongsToMany(Ebook::class);
    }
}

