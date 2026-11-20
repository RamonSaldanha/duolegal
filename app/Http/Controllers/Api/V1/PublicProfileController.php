<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\PublicProfileController as WebPublicProfileController;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Perfil público para o app, aberto pelo ranking. Os campos são os da web, escolhidos um a
 * um no controller dela.
 */
class PublicProfileController extends WebPublicProfileController
{
    /** GET /v1/profiles/{user:uuid} */
    public function profile(Request $request, User $user): JsonResponse
    {
        return response()->json($this->payload($request, $user));
    }
}
