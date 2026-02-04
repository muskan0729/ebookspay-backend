<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Ebook;

class EbookController extends Controller
{
    public function store(Request $request)
{
    $request->validate([
        'title' => 'required',
        'description' => 'required',
        'price' => 'required|numeric',
        'ebook_file' => 'required|file|mimes:pdf',
        'categories' => 'required|array',
        'images.*' => 'image|max:2048',
    ]);

    $ebookFile = $request->file('ebook_file')->store('ebooks');

    $ebook = Ebook::create([
        'title' => $request->title,
        'slug' => Str::slug($request->title),
        'description' => $request->description,
        'price' => $request->price,
        'ebook_file' => $ebookFile,
    ]);

    // Attach categories
    $ebook->categories()->sync($request->categories);

    // Multiple images
    if ($request->hasFile('images')) {
        foreach ($request->file('images') as $image) {
            $path = $image->store('ebook-images');
            $ebook->images()->create([
                'image_path' => $path
            ]);
        }
    }

    return response()->json([
        'message' => 'Ebook created successfully',
        'ebook' => $ebook->load('categories','images')
    ], 201);
}

public function update(Request $request, $id)
{
    $ebook = Ebook::findOrFail($id);

    $ebook->update($request->only([
        'title','description','price'
    ]));

    if ($request->categories) {
        $ebook->categories()->sync($request->categories);
    }

    if ($request->hasFile('images')) {
        foreach ($request->file('images') as $image) {
            $path = $image->store('ebook-images');
            $ebook->images()->create(['image_path'=>$path]);
        }
    }

    return response()->json([
        'message' => 'Ebook updated',
        'ebook' => $ebook->load('categories','images')
    ]);
}

public function show($id)
{
    return Ebook::with(['categories','images'])->findOrFail($id);
}

public function index()
{
    return Ebook::with(['categories','images'])->latest()->get();
}

public function destroy($id)
{
    $ebook = Ebook::findOrFail($id);
    $ebook->delete();

    return response()->json(['message'=>'Ebook deleted']);
}


}
