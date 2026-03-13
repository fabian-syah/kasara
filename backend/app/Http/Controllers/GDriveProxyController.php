<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GDriveProxyController extends Controller
{
    /**
     * Proxies the PDF upload to Google Apps Script to bypass CORS.
     */
    public function upload(Request $request)
    {
        $request->validate([
            'fileBase64' => 'required|string',
            'filename' => 'required|string',
        ]);

        Log::info('GDrive Proxy: Starting upload for ' . $request->filename);

        $scriptUrl = 'https://script.google.com/macros/s/AKfycbxJ4V2A4uX7Ft_FFsrRCx9A86KA9ev0eWZTxcHlmR-lBFzjeJthLWVrN7mDDBFJfmyM/exec';

        try {
            $response = Http::timeout(60)
                ->withOptions(['allow_redirects' => true])
                ->post($scriptUrl, [
                    'fileBase64' => $request->fileBase64,
                    'filename' => $request->filename,
                ]);

            Log::info('GDrive Proxy Status: ' . $response->status());

            if ($response->successful()) {
                Log::info('GDrive Proxy Success');
                return response()->json($response->json());
            }

            Log::error('GDrive Proxy Error Status: ' . $response->status());
            Log::error('GDrive Proxy Error Body: ' . $response->body());

            return response()->json([
                'success' => false,
                'error' => 'Failed to upload to Google Drive via proxy. Status: ' . $response->status()
            ], 500);

        } catch (\Exception $e) {
            Log::error('GDrive Proxy Exception: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
