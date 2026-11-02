<?php

namespace App\Http\Controllers;

use App\Models\Discipline;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DisciplineBadgeController extends Controller
{
    /** Pública porque o app carrega a imagem sem token. A URL muda junto com badge_version. */
    public function show(Discipline $discipline, int $stars): BinaryFileResponse
    {
        $disk = Storage::disk('local');
        $path = "{$discipline->badgeImagesDirectory()}/{$stars}.png";

        abort_unless($disk->exists($path), 404);

        return response()->file($disk->path($path), [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'public, max-age=31536000, immutable',
        ]);
    }
}
