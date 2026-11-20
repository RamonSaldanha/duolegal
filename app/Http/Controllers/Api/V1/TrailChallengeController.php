<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\BetaCheckpointController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Desafio do quadrado para o app. Os dados são os da web, mais `summary_blocks` (o resumo em
 * blocos, para o app desenhar e fazer a leitura guiada); `summary` e `answer` são os da web,
 * herdados.
 */
class TrailChallengeController extends BetaCheckpointController
{
    /** GET /v1/trail/challenges/{checkpoint} */
    public function challenge(Request $request, string $checkpoint): JsonResponse
    {
        $payload = $this->checkpointPayload($request->user(), $checkpoint, withBlocks: true);

        return is_array($payload) ? response()->json($payload) : TrailController::refusal($payload);
    }
}
