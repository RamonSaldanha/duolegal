<?php

namespace App\Http\Controllers;

use App\Models\LawArticle;
use App\Models\LegalReference;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;

class WelcomeController extends Controller
{
    public function index()
    {
        // Redirecionar usuários logados diretamente para /play
        if (Auth::check()) {
            return redirect()->route('play.map');
        }

        $articlesCount = LawArticle::where('is_active', true)->count();
        $firstFourLaws = LegalReference::where('is_active', true)
            ->orderBy('id', 'asc')
            ->take(4)
            ->get(['name', 'uuid']);
        
        return Inertia::render('Welcome', [
            'articlesCount' => $articlesCount,
            'firstFourLaws' => $firstFourLaws
        ]);
    }
}
