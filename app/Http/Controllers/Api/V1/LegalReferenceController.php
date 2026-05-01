<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\LegalReference;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LegalReferenceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $userReferenceIds = $user->legalReferences()->pluck('legal_references.id')->toArray();

        $references = LegalReference::where('is_active', true)
            ->orderBy('name')
            ->get()
            ->map(fn ($ref) => [
                'id' => $ref->id,
                'uuid' => $ref->uuid,
                'name' => $ref->name,
                'description' => $ref->description,
                'difficulty_level' => $ref->difficulty_level,
                'is_selected' => in_array($ref->id, $userReferenceIds),
            ]);

        return response()->json([
            'legal_references' => $references,
            'selected_ids' => $userReferenceIds,
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'integer|exists:legal_references,id',
        ]);

        $user = $request->user();
        $user->legalReferences()->sync($request->ids);

        return response()->json([
            'message' => 'Preferências salvas com sucesso.',
            'selected_ids' => $request->ids,
        ]);
    }
}
