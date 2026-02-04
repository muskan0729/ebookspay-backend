<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;


class Ebook extends Model
{
    protected $fillable = [
        'title','slug','description','price','ebook_file'
    ];

    public function images()
    {
        return $this->hasMany(EbookImage::class);
    }

    public function categories()
    {
        return $this->belongsToMany(Category::class);
    }
}
