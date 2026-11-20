<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\BetaExamController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Dia da prova para o app. Os dados são os da web; `start`, `answer` e `finish` são os da
 * web, herdados. O prazo é do servidor (`deadline_at`): o relógio do app só mostra.
 */
class TrailExamController extends BetaExamController
{
    /** GET /v1/trail/exams/{exam} */
    public function exam(Request $request, string $exam): JsonResponse
    {
        $payload = $this->examPayload($request->user(), $exam);

        return is_array($payload) ? response()->json($payload) : TrailController::refusal($payload);
    }
}
