<?php

namespace App\Http\Controllers\Api\v1;

use App\Models\BioTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class BioTemplateController extends Controller
{
    /**
     * Public — active bio templates for the app.
     * Optionally filter by ?gender=male|female (returns matching + gender-neutral).
     */
    public function index(Request $request): JsonResponse
    {
        $query = BioTemplate::active();

        if ($gender = $request->query('gender')) {
            $query->where(function ($q) use ($gender) {
                $q->whereNull('gender')->orWhere('gender', $gender);
            });
        }

        $templates = $query->get(['id', 'text', 'sort_order']);

        return response()->json([
            'status'  => true,
            'message' => 'Bio templates fetched successfully',
            'data'    => $templates,
        ]);
    }

    // ─── Admin CRUD ─────────────────────────────────────────

    public function adminIndex(): JsonResponse
    {
        $templates = BioTemplate::orderBy('sort_order')->get();

        return response()->json([
            'status' => true,
            'data'   => $templates,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'text'       => 'required|string|max:500',
            'gender'     => 'nullable|in:male,female',
            'sort_order' => 'integer|min:0',
            'is_active'  => 'boolean',
        ]);

        $template = BioTemplate::create($request->only('text', 'gender', 'sort_order', 'is_active'));

        return response()->json([
            'status'  => true,
            'message' => 'Bio template created',
            'data'    => $template,
        ], 201);
    }

    public function update(Request $request, BioTemplate $bioTemplate): JsonResponse
    {
        $request->validate([
            'text'       => 'sometimes|string|max:500',
            'gender'     => 'nullable|in:male,female',
            'sort_order' => 'sometimes|integer|min:0',
            'is_active'  => 'sometimes|boolean',
        ]);

        $bioTemplate->update($request->only('text', 'gender', 'sort_order', 'is_active'));

        return response()->json([
            'status'  => true,
            'message' => 'Bio template updated',
            'data'    => $bioTemplate,
        ]);
    }

    public function destroy(BioTemplate $bioTemplate): JsonResponse
    {
        $bioTemplate->delete();

        return response()->json([
            'status'  => true,
            'message' => 'Bio template deleted',
        ]);
    }
}
