<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Ebook;
use App\Models\EbookImage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class EbookImageController extends Controller
{
    // List images of an ebook
public function index($ebookId)
{
    try {
        $ebook = Ebook::with('images')->findOrFail($ebookId);

        $images = $ebook->images->map(function ($img) {
            $img->image_url = $img->image_path ? asset($img->image_path) : null;
            return $img;
        });

        return response()->json([
            'success' => true,
            'data' => $images
        ]);
    } catch (\Exception $e) {
        return response()->json(['error' => $e->getMessage()], 500);
    }
}

    // public function store(Request $request, $ebookId)
    // {
    //     try {
    //         $request->validate([
    //             'images'   => 'required|array|min:1',
    //             'images.*' => 'image|mimes:jpg,jpeg,png,webp|max:2048',
    //         ]);

    //         $ebook = Ebook::findOrFail($ebookId);
    //         $savedImages = [];

    //         foreach ($request->file('images') as $image) {
    //             // Use same logic as EbookController
    //             $imageName = pathinfo($image->getClientOriginalName(), PATHINFO_FILENAME);
    //             $extension = $image->getClientOriginalExtension();
    //             $filename = $imageName . '_' . $ebook->id . '.' . $extension;

    //             // Store in same folder 'ebook-images' (not 'ebook_images')
    //             $path = $image->storeAs('ebook-images', $filename);

    //             $savedImages[] = EbookImage::create([
    //                 'ebook_id'   => $ebook->id,
    //                 'image_path' => $path
    //             ]);
    //         }

    //         return response()->json([
    //             'success' => true,
    //             'message' => 'Images uploaded successfully',
    //             'data'    => $savedImages
    //         ], 201);
    //     } catch (\Exception $e) {
    //         return response()->json(['error' => $e->getMessage()], 500);
    //     }
    // }
    
    public function store(Request $request, $ebookId)
{
    try {
        $request->validate([
            'images'   => 'required|array|min:1',
            'images.*' => 'image|mimes:jpg,jpeg,png,webp|max:2048',
        ]);

        $ebook = Ebook::findOrFail($ebookId);
        $savedImages = [];

        foreach ($request->file('images') as $image) {

            $imageName = pathinfo($image->getClientOriginalName(), PATHINFO_FILENAME);
            $extension = $image->getClientOriginalExtension();

            $filename = $imageName . '_' . time() . '_' . $ebook->id . '.' . $extension;

            // 👇 Public folder path
            $destinationPath = public_path('uploads/ebook-images');

            // 👇 Create folder if not exists
            if (!file_exists($destinationPath)) {
                mkdir($destinationPath, 0755, true);
            }

            // 👇 Move file to public folder
            $image->move($destinationPath, $filename);

            // 👇 Save relative path in DB
            $relativePath = 'uploads/ebook-images/' . $filename;

            $savedImages[] = EbookImage::create([
                'ebook_id'   => $ebook->id,
                'image_path' => $relativePath
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Images uploaded successfully',
            'data'    => $savedImages
        ], 201);

    } catch (\Exception $e) {
        return response()->json(['error' => $e->getMessage()], 500);
    }
}

public function update(Request $request, $id)
{
    try {
        $request->validate([
            'image' => 'required|image|mimes:jpg,jpeg,png,webp|max:2048',
        ]);

        $image = EbookImage::findOrFail($id);
        $ebook = $image->ebook;

        // ✅ Delete old image from PUBLIC folder
        if (!empty($image->image_path)) {
            $oldPath = public_path($image->image_path); // image_path = uploads/ebook-images/xxx.jpg
            if (file_exists($oldPath)) {
                @unlink($oldPath);
            }
        }

        $newImage = $request->file('image');

        // ✅ Clean filename (remove spaces/special chars)
        $base = pathinfo($newImage->getClientOriginalName(), PATHINFO_FILENAME);
        $base = preg_replace('/[^A-Za-z0-9_-]+/', '-', $base);

        $extension = $newImage->getClientOriginalExtension();

        // ✅ Unique name (avoid overwrite)
        $filename = $base . '_' . time() . '_' . $ebook->id . '.' . $extension;

        // ✅ Public destination
        $destinationPath = public_path('uploads/ebook-images');
        if (!file_exists($destinationPath)) {
            mkdir($destinationPath, 0755, true);
        }

        // ✅ Move to public folder
        $newImage->move($destinationPath, $filename);

        // ✅ Save relative path in DB
        $relativePath = 'uploads/ebook-images/' . $filename;

        $image->update([
            'image_path' => $relativePath
        ]);

        // ✅ Optional: return URL also
        $image->image_url = asset($image->image_path);

        return response()->json([
            'success' => true,
            'message' => 'Image updated successfully',
            'data' => $image
        ]);

    } catch (\Exception $e) {
        return response()->json(['error' => $e->getMessage()], 500);
    }
}

    // Show single image
public function show($id)
{
    try {
        $image = EbookImage::findOrFail($id);

        // ✅ add full url (optional but recommended)
        $image->image_url = $image->image_path ? asset($image->image_path) : null;

        return response()->json([
            'success' => true,
            'data' => $image
        ]);
    } catch (\Exception $e) {
        return response()->json(['error' => $e->getMessage()], 500);
    }
}

// Delete image
public function destroy($id)
{
    try {
        $image = EbookImage::findOrFail($id);

        // ✅ Delete from PUBLIC folder (not Storage)
        if (!empty($image->image_path)) {
            $filePath = public_path($image->image_path); // uploads/ebook-images/xxx.jpg
            if (file_exists($filePath)) {
                @unlink($filePath);
            }
        }

        $image->delete();

        return response()->json([
            'success' => true,
            'message' => 'Image deleted successfully'
        ]);
    } catch (\Exception $e) {
        return response()->json(['error' => $e->getMessage()], 500);
    }
}
}