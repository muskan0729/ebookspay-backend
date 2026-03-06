<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\File;

class ImageMoveController extends Controller
{
    /**
     * Move images from internal/public to storage/app/public
     * URL = /move-images
     */
    public function move()
    {
          $to = storage_path('app/ebook-images');
          $from = storage_path('app/public/ebook-images');

        if (!File::exists($from)) {
            return response()->json([
                'status' => false,
                'message' => 'Source folder not found'
            ], 404);
        }

        File::ensureDirectoryExists($to);

        foreach (File::files($from) as $file) {
            File::move(
                $file->getPathname(),
                $to . '/' . $file->getFilename()
            );
        }

        return response()->json([
            'status' => true,
            'message' => 'Images moved successfully'
        ]);
    }
}
