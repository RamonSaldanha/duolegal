<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\BetaPlayController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Mapa novo (quadrados de 4 fases) para o app. Os dados são os mesmos que a web recebe em
 * /play da web, montados pelos mesmos métodos; `fail` e `finish` são os da web, herdados.
 */
class TrailController extends BetaPlayController
{
    /** GET /v1/trail?before=&after= */
    public function index(Request $request): JsonResponse
    {
        return response()->json($this->mapPayload($request->user(), $request));
    }

    /** GET /v1/trail/phases/{phaseId} */
    public function phase(Request $request, int $phaseId): JsonResponse
    {
        $payload = $this->phasePayload($request->user(), $phaseId);

        return is_array($payload) ? response()->json($payload) : self::refusal($payload);
    }

    /** GET /v1/trail/energy: a tela sem energia (a energia, a revisão sugerida e o anúncio). */
    public function energy(Request $request): JsonResponse
    {
        return response()->json($this->energyStatus($request->user()));
    }

    /**
     * Por que algo do mapa não abre, no formato que o app lê: `no_energy` (403) ou
     * `unavailable` (409, bloqueado ou já sem nada a jogar).
     */
    public static function refusal(string $code): JsonResponse
    {
        return $code === 'no_energy'
            ? response()->json(['message' => 'Você está sem energia.', 'code' => 'no_energy'], 403)
            : response()->json(['message' => 'Este passo do mapa não está disponível.', 'code' => 'unavailable'], 409);
    }
}
