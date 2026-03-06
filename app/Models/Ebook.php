<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;



class Ebook extends Model
{
    protected $fillable = [
        'title',
        'slug',
        'description',
        'price',
        'ebook_file',
        'status'
    ];

    
    protected $hidden = ['images', 'ebook_file'];

    protected $appends = ['image', 'download_url'];


    public function images()
    {
        return $this->hasMany(EbookImage::class);
    }

    public function categories()
    {
        return $this->belongsToMany(Category::class, 'ebook_category', 'ebook_id', 'category_id');
    }


    public function orders()
    {
        return $this->belongsToMany(Order::class, 'cart_items', 'ebook_id', 'cart_id')
            ->withPivot('quantity', 'price');
    }

    public function getImageAttribute()
    {
        $image = $this->images->first();

        return $image
            ? asset('storage/' . $image->image_path)
            : asset('storage/defaults/ebook.png');
    }
    
    public function getDownloadUrlAttribute()
{
    // Check if current user has purchased this ebook
    $user = auth()->user(); // or pass user explicitly
    if (!$user) return null;

    $hasPurchased = $user->orders()
        ->whereHas('cart.items', function($q) {
            $q->where('ebook_id', $this->id);
        })
        ->where('status', 'completed')
        ->exists();

    return $hasPurchased
        ? asset('storage/' . $this->ebook_file)
        : null;
}



}
