<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

class ArtisanController extends Controller
{
    public function list()
    {
        return response()->json([
            'status' => 'success',
            'commands' => array_keys(Artisan::all())
        ]);
    }

    public function run(Request $request)
    {
        // 🔐 HARD SECURITY
        if ($request->header('X-ARTISAN-KEY') !== env('ARTISAN_API_KEY')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $request->validate([
            'command' => 'required|string',
            'options' => 'nullable|array'
        ]);

        $command = $request->command;
        $options = $request->options ?? [];

        // ❌ BLOCK VERY DANGEROUS COMMANDS
        $blocked = [
            'env',
            'tinker',
            'serve',
            'test',
            'key:generate',
            'queue:listen',
            'queue:work',
        ];

        foreach ($blocked as $blockedCmd) {
            if (str_starts_with($command, $blockedCmd)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Command blocked for security'
                ], 403);
            }
        }

        try {
            $output = new BufferedOutput;

            Artisan::call($command, $options, $output);

            return response()->json([
                'status' => 'success',
                'command' => $command,
                'output' => $output->fetch()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
