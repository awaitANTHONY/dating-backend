<?php

namespace App\Http\Controllers\Api\v1;

use App\Models\MoodSuggestion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class MoodSuggestionController extends Controller
{
    /**
     * Public — active mood suggestions for the app.
     */
    public function index(): JsonResponse
    {
        $suggestions = MoodSuggestion::active()->get(['id', 'text', 'sort_order']);

        return response()->json([
            'status'  => true,
            'message' => 'Mood suggestions fetched successfully',
            'data'    => $suggestions,
        ]);
    }

    // ─── Admin CRUD ─────────────────────────────────────────

    public function adminIndex(): JsonResponse
    {
        $suggestions = MoodSuggestion::orderBy('sort_order')->get();

        return response()->json([
            'status'  => true,
            'data'    => $suggestions,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'text'       => 'required|string|max:50',
            'sort_order' => 'integer|min:0',
            'is_active'  => 'boolean',
        ]);

        $suggestion = MoodSuggestion::create($request->only('text', 'sort_order', 'is_active'));

        return response()->json([
            'status'  => true,
            'message' => 'Mood suggestion created',
            'data'    => $suggestion,
        ], 201);
    }

    public function update(Request $request, MoodSuggestion $moodSuggestion): JsonResponse
    {
        $request->validate([
            'text'       => 'sometimes|string|max:50',
            'sort_order' => 'sometimes|integer|min:0',
            'is_active'  => 'sometimes|boolean',
        ]);

        $moodSuggestion->update($request->only('text', 'sort_order', 'is_active'));

        return response()->json([
            'status'  => true,
            'message' => 'Mood suggestion updated',
            'data'    => $moodSuggestion,
        ]);
    }

    public function destroy(MoodSuggestion $moodSuggestion): JsonResponse
    {
        $moodSuggestion->delete();

        return response()->json([
            'status'  => true,
            'message' => 'Mood suggestion deleted',
        ]);
    }
}
