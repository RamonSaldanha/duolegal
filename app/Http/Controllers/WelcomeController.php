<?php

namespace App\Http\Controllers;

use App\Models\LawArticle;
use App\Models\LegalReference;
use Inertia\Inertia;

class WelcomeController extends Controller
{
    public function index()
    {
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
