<?php

namespace App\Http\Controllers;

use App\Models\LawArticle;
use Inertia\Inertia;

class WelcomeController extends Controller
{
    public function index()
    {
        $articlesCount = LawArticle::where('is_active', true)->count();
        
        return Inertia::render('Welcome', [
            'articlesCount' => $articlesCount
        ]);
    }
}
