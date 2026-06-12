<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class ProjectFileChunkUploadController extends Controller
{
    public function uploadChunk(Request $request)
    {
        $validated = $request->validate([
            'upload_id' => ['required'],
            'chunk_index' => ['required', 'integer', 'min:1'],
            'total_chunks' => ['required', 'integer', 'min:1'],
            'original_name' => ['required', 'string'],
            'file' => ['required', 'file'],
        ]);

        @set_time_limit(120);

        $chunk = $request->file('file');
        $bytes = file_get_contents($chunk->getRealPath());

        $base = rtrim((string) config('services.api_project'), '/').'/';
        $response = Http::timeout(120)
            ->attach('file', $bytes, $validated['original_name'])
            ->post($base.'upload-chunks', [
                'upload_id' => $validated['upload_id'],
                'chunk_index' => ((int) $validated['chunk_index']) - 1,
                'total_chunks' => (int) $validated['total_chunks'],
                'original_name' => $validated['original_name'],
            ]);

        return response()->json(
            $response->json() ?? ['status' => $response->status()],
            $response->status()
        );
    }
}
