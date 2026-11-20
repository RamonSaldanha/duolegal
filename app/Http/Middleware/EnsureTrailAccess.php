<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Libera o mapa novo só para quem config('trail.audience') deixa jogar. Na web volta para o
 * mapa atual; na API responde 403 com o código `trail_disabled`.
 */
class EnsureTrailAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->usesTrailMap()) {
            return $next($request);
        }

        // Só a API do app responde em JSON; a web segue como era com o middleware `admin`
        if ($request->is('api/*')) {
            return response()->json([
                'message' => 'O mapa novo ainda não está liberado para a sua conta.',
                'code' => 'trail_disabled',
            ], 403);
        }

        return redirect()->route('play.map')->with('error', 'Acesso não autorizado.');
    }
}
