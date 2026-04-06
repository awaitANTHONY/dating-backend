<?php

namespace App\Http\Controllers\Api\v1\Admin;

use App\Models\ContactPlatform;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class ContactPlatformController extends Controller
{
    public function index(): JsonResponse
    {
        $platforms = ContactPlatform::orderBy('sort_order')->get();

        return response()->json([
            'status' => true,
            'data'   => $platforms,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name'        => 'required|string|max:50|unique:contact_platforms,name',
            'icon'        => 'required|string|max:100',
            'placeholder' => 'nullable|string|max:100',
            'sort_order'  => 'integer|min:0',
            'is_active'   => 'boolean',
        ]);

        $platform = ContactPlatform::create($request->only(
            'name', 'icon', 'placeholder', 'sort_order', 'is_active'
        ));

        return response()->json([
            'status'  => true,
            'message' => 'Contact platform created',
            'data'    => $platform,
        ], 201);
    }

    public function update(Request $request, ContactPlatform $contactPlatform): JsonResponse
    {
        $request->validate([
            'name'        => 'sometimes|string|max:50|unique:contact_platforms,name,' . $contactPlatform->id,
            'icon'        => 'sometimes|string|max:100',
            'placeholder' => 'nullable|string|max:100',
            'sort_order'  => 'sometimes|integer|min:0',
            'is_active'   => 'sometimes|boolean',
        ]);

        $contactPlatform->update($request->only(
            'name', 'icon', 'placeholder', 'sort_order', 'is_active'
        ));

        return response()->json([
            'status'  => true,
            'message' => 'Contact platform updated',
            'data'    => $contactPlatform,
        ]);
    }

    public function destroy(ContactPlatform $contactPlatform): JsonResponse
    {
        $contactPlatform->delete();

        return response()->json([
            'status'  => true,
            'message' => 'Contact platform deleted',
        ]);
    }
}
