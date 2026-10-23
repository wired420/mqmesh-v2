<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FileVersion;
use App\Models\Zone;
use App\Services\FileLibrary;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UploadController extends Controller
{
    /**
     * Upload a new community version of a zone's mesh or doors file.
     */
    public function store(Request $request, Zone $zone, FileLibrary $library): JsonResponse
    {
        $data = $request->validate([
            'type' => ['required', 'in:mesh,doors'],
            'file' => ['required', 'file', 'max:'.config('mqmesh.max_upload_kb')],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $path = $request->file('file')->getRealPath();

        if ($problem = $library->problemWith($data['type'], $path)) {
            return response()->json(['message' => $problem, 'errors' => ['file' => [$problem]]], 422);
        }

        $duplicate = $zone->versions()->where('type', $data['type'])->where('sha256', hash_file('sha256', $path))
            ->whereNotIn('status', [FileVersion::STATUS_REJECTED, FileVersion::STATUS_WITHDRAWN])->first();

        if ($duplicate) {
            return response()->json(['message' => "This exact file is already version {$duplicate->version}.", 'version' => $duplicate->version], 409);
        }

        $user = $request->user();

        $file = $library->store($zone, $data['type'], $path, [
            'source' => FileVersion::SOURCE_UPLOAD,
            'status' => $user->skipsReview() ? FileVersion::STATUS_APPROVED : FileVersion::STATUS_PENDING,
            'user' => $user,
            'notes' => $data['notes'] ?? null,
            'original_name' => $request->file('file')->getClientOriginalName(),
        ]);

        return response()->json([
            'id' => $file->id,
            'version' => $file->version,
            'status' => $file->status,
            'md5' => $file->md5,
            'sha256' => $file->sha256,
            'url' => route('zones.show', $zone),
        ], 201);
    }
}
