<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

/**
 * Rotas REST usadas pelos agentes de IA que gravam material de estudo.
 *
 * Exige usuário admin e token com a habilidade certa. As respostas de erro destas rotas
 * saem sempre em JSON (ver bootstrap/app.php): quem chama é um agente com curl, e um
 * redirecionamento para o login não diria nada a ele.
 */
class StudyMaterialAgent
{
    public const ABILITY = 'study-materials';

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['message' => 'Token ausente ou inválido.'], 401);
        }

        if (! $user->isAdmin()) {
            return response()->json(['message' => 'Este token não pertence a um administrador.'], 403);
        }

        // Conferido na lista literal: o token do app (`mobile`) tem a habilidade "*", que
        // passaria em tokenCan(), e ele não deve servir aqui.
        $token = $user->currentAccessToken();

        if (! $token instanceof PersonalAccessToken || ! in_array(self::ABILITY, $token->abilities ?? [], true)) {
            return response()->json([
                'message' => 'Este token não tem a habilidade "'.self::ABILITY.'". Gere um token na página "Instalar a skill" do admin.',
            ], 403);
        }

        return $next($request);
    }
}
