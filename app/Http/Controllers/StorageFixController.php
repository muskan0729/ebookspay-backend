<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

class StorageFixController extends Controller
{
    /**
     * Remove old storage link and recreate
     * URL = /fix-storage
     */
    public function fix()
    {
        $path = public_path('storage');

        // Remove existing link/folder
        if (File::exists($path)) {
            File::delete($path);
        }

        // Create storage symlink
        Artisan::call('storage:link');

        return response()->json([
            'status' => true,
            'message' => 'Storage link fixed successfully'
        ]);
    }
}
