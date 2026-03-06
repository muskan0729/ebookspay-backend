<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Ebook;
use Illuminate\Support\Str;
use App\Filters\EbookFilter;
use Illuminate\Support\Facades\Storage;


class EbookController extends Controller
{
   public function store(Request $request)
{
    try {
        $request->validate([
            'title' => 'required',
            'description' => 'required',
            'price' => 'required|numeric',
            'ebook_file' => 'required|file|mimes:pdf',
            'categories' => 'required|array',
            'images.*' => 'image|max:2048',
        ]);

        // First create ebook without ebook_file
        $ebook = Ebook::create([
            'title' => $request->title,
            'slug' => Str::slug($request->title),
            'description' => $request->description,
            'price' => $request->price,
        ]);

        // Handle ebook file - store in public/uploads/ebooks/
        if ($request->hasFile('ebook_file')) {
            $file = $request->file('ebook_file');
            $originalName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
            $extension = $file->getClientOriginalExtension();
            $filename = $originalName . '_' . $ebook->id . '.' . $extension;
            
            // Store in public/uploads/ebooks/
            $destinationPath = public_path('uploads/ebooks');
            if (!file_exists($destinationPath)) {
                mkdir($destinationPath, 0755, true);
            }
            
            $file->move($destinationPath, $filename);
            
            // Save relative path in DB
            $ebook->update(['ebook_file' => 'uploads/ebooks/' . $filename]);
        }

        // Attach categories
        $ebook->categories()->sync($request->categories);

        // Multiple images - store in public/uploads/ebook-images/
        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $image) {
                $imageName = pathinfo($image->getClientOriginalName(), PATHINFO_FILENAME);
                $extension = $image->getClientOriginalExtension();
                $filename = $imageName . '_' . $ebook->id . '.' . $extension;

                $destinationPath = public_path('uploads/ebook-images');
                if (!file_exists($destinationPath)) {
                    mkdir($destinationPath, 0755, true);
                }
                
                $image->move($destinationPath, $filename);
                
                $ebook->images()->create(['image_path' => 'uploads/ebook-images/' . $filename]);
            }
        }

        return response()->json([
            'message' => 'Ebook created successfully',
            'ebook' => $ebook->load('categories','images')
        ], 201);
    } catch (\Exception $e) {
        return response()->json(['error' => $e->getMessage()], 500);
    }
}


public function update(Request $request, $id)
{
    try {
        $ebook = Ebook::findOrFail($id);

        $request->validate([
            'title' => 'sometimes|required',
            'description' => 'sometimes|required',
            'price' => 'sometimes|required|numeric',
            'categories' => 'sometimes|array',
            'ebook_file' => 'sometimes|file|mimes:pdf',
            'images.*' => 'image|max:2048',
        ]);

        $data = $request->only(['title','description','price']);

        // ====== Handle main ebook file (PDF) ======
        if ($request->hasFile('ebook_file')) {
            // Delete old file if exists
            if ($ebook->ebook_file) {
                $oldFilePath = public_path($ebook->ebook_file);
                if (file_exists($oldFilePath)) {
                    @unlink($oldFilePath);
                }
            }

            $file = $request->file('ebook_file');
            $originalName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
            $extension = $file->getClientOriginalExtension();
            $filename = $originalName . '_' . $ebook->id . '.' . $extension;

            // Store in public/uploads/ebooks/
            $destinationPath = public_path('uploads/ebooks');
            if (!file_exists($destinationPath)) {
                mkdir($destinationPath, 0755, true);
            }
            
            $file->move($destinationPath, $filename);
            
            $data['ebook_file'] = 'uploads/ebooks/' . $filename;
        }

        $ebook->update($data);

        // ====== Sync categories ======
        if ($request->filled('categories')) {
            $ebook->categories()->sync($request->categories);
        }

        // ====== Handle images ======
        if ($request->hasFile('images')) {
            // Delete old images from public folder and DB
            foreach ($ebook->images as $oldImage) {
                $oldFilePath = public_path($oldImage->image_path);
                if (file_exists($oldFilePath)) {
                    @unlink($oldFilePath);
                }
                $oldImage->delete();
            }

            // Upload new images
            foreach ($request->file('images') as $image) {
                $imageName = pathinfo($image->getClientOriginalName(), PATHINFO_FILENAME);
                $extension = $image->getClientOriginalExtension();
                $filename = $imageName . '_' . $ebook->id . '.' . $extension;

                $destinationPath = public_path('uploads/ebook-images');
                if (!file_exists($destinationPath)) {
                    mkdir($destinationPath, 0755, true);
                }
                
                $image->move($destinationPath, $filename);
                
                $ebook->images()->create(['image_path' => 'uploads/ebook-images/' . $filename]);
            }
        }

        return response()->json([
            'message' => 'Ebook updated',
            'ebook' => $ebook->load('categories','images')
        ]);
    } catch (\Exception $e) {
        return response()->json(['error' => $e->getMessage()], 500);
    }
}


public function show($id)
{
    try {
        $ebook = Ebook::with(['categories','images'])->findOrFail($id);
        
        // Add full URLs for images
        if ($ebook->images) {
            foreach ($ebook->images as $image) {
                $image->image_url = $image->image_path ? asset($image->image_path) : null;
            }
        }
        
        // Add full URL for ebook file
        if ($ebook->ebook_file) {
            $ebook->ebook_file_url = asset($ebook->ebook_file);
        }
        
        return response()->json($ebook);
    } catch (\Exception $e) {
        return response()->json(['error' => $e->getMessage()], 500);
    }
}

public function index(Request $request)
{
    try {
        $query = Ebook::with(['categories','images']);
        $query = (new EbookFilter)->apply($query, $request->all());

        $ebooks = $query->latest()->paginate(10);

        // Add full URLs for images and files
        foreach ($ebooks as $ebook) {
            if ($ebook->images) {
                foreach ($ebook->images as $image) {
                    $image->image_url = $image->image_path ? asset($image->image_path) : null;
                }
            }
            if ($ebook->ebook_file) {
                $ebook->ebook_file_url = asset($ebook->ebook_file);
            }
        }

        return response()->json([
            'success' => true,
            'data' => $ebooks
        ]);
    } catch (\Exception $e) {
        return response()->json(['error' => $e->getMessage()], 500);
    }
}


public function destroy($id)
{
    try {
        $ebook = Ebook::findOrFail($id);
        
        // Delete associated images from public folder
        foreach ($ebook->images as $image) {
            $imagePath = public_path($image->image_path);
            if (file_exists($imagePath)) {
                @unlink($imagePath);
            }
            $image->delete();
        }
        
        // Delete ebook file from public folder
        if ($ebook->ebook_file) {
            $filePath = public_path($ebook->ebook_file);
            if (file_exists($filePath)) {
                @unlink($filePath);
            }
        }
        
        $ebook->delete();

        return response()->json(['message' => 'Ebook deleted']);
    } catch (\Exception $e) {
        return response()->json(['error' => $e->getMessage()], 500);
    }
}


// ================= UPDATE EBOOK STATUS (ACTIVE/INACTIVE) =================
public function updateStatus(Request $request, $id)
{
    try {
        $request->validate([
            'status' => 'required|in:active,inactive'
        ]);

        $ebook = Ebook::findOrFail($id);
        
        $ebook->update([
            'status' => $request->status
        ]);

        return response()->json([
            'message' => 'Ebook status updated successfully',
            'ebook' => $ebook->load('categories','images'),
            'status' => $ebook->status
        ]);
    } catch (\Exception $e) {
        return response()->json(['error' => $e->getMessage()], 500);
    }
}

}