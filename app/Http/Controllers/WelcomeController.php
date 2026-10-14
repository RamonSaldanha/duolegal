<?php

namespace App\Http\Controllers;

use App\Models\LawArticle;
use App\Models\LegalReference;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class WelcomeController extends Controller
{
    public function index()
    {
        // Redirecionar usuários logados diretamente para /play
        if (Auth::check()) {
            return redirect()->route('play.map');
        }

        $articlesCount = LawArticle::where('is_active', true)->count();
        $laws = LegalReference::where('is_active', true)
            ->orderBy('id', 'asc')
            ->get(['name', 'uuid', 'slug']);

        return Inertia::render('Welcome', [
            'articlesCount' => $articlesCount,
            'firstFourLaws' => $laws->take(4)->values(),
            // Faixa animada de leis na landing
            'laws' => $laws,
        ]);
    }
}
