<?php

namespace App\Http\Controllers;

use App\Models\LegalReference;
use App\Models\LawArticle;
use Illuminate\Http\Response;

class SitemapController extends Controller
{
    /**
     * Gera o sitemap principal em XML
     */
    public function index()
    {
        $sitemap = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $sitemap .= '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        
        // Sitemap principal das páginas estáticas
        $sitemap .= '  <sitemap>' . "\n";
        $sitemap .= '    <loc>' . route('sitemap.main') . '</loc>' . "\n";
        $sitemap .= '    <lastmod>' . now()->toISOString() . '</lastmod>' . "\n";
        $sitemap .= '  </sitemap>' . "\n";
        
        // Sitemap das leis
        $sitemap .= '  <sitemap>' . "\n";
        $sitemap .= '    <loc>' . route('sitemap.laws') . '</loc>' . "\n";
        $sitemap .= '    <lastmod>' . now()->toISOString() . '</lastmod>' . "\n";
        $sitemap .= '  </sitemap>' . "\n";
        
        // Sitemap dos artigos
        $sitemap .= '  <sitemap>' . "\n";
        $sitemap .= '    <loc>' . route('sitemap.articles') . '</loc>' . "\n";
        $sitemap .= '    <lastmod>' . now()->toISOString() . '</lastmod>' . "\n";
        $sitemap .= '  </sitemap>' . "\n";
        
        $sitemap .= '</sitemapindex>';
        
        return response($sitemap, 200, [
            'Content-Type' => 'application/xml',
            'Cache-Control' => 'public, max-age=3600'
        ]);
    }

    /**
     * Sitemap das páginas principais
     */
    public function main()
    {
        $sitemap = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $sitemap .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        
        // Página inicial
        $sitemap .= $this->addUrl(route('home'), '1.0', 'daily');
        
        // Páginas públicas principais
        $sitemap .= $this->addUrl(route('public.laws'), '0.9', 'daily');
        $sitemap .= $this->addUrl(route('public.search'), '0.8', 'weekly');
        
        // Páginas legais
        $sitemap .= $this->addUrl(route('privacy-policy'), '0.3', 'monthly');
        $sitemap .= $this->addUrl(route('terms'), '0.3', 'monthly');
        $sitemap .= $this->addUrl(route('cookies'), '0.3', 'monthly');
        
        $sitemap .= '</urlset>';
        
        return response($sitemap, 200, [
            'Content-Type' => 'application/xml',
            'Cache-Control' => 'public, max-age=3600'
        ]);
    }

    /**
     * Sitemap das leis
     */
    public function laws()
    {
        $sitemap = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $sitemap .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        
        $laws = LegalReference::where('is_active', true)
            ->orderBy('name')
            ->get();
            
        foreach ($laws as $law) {
            $sitemap .= $this->addUrl(
                route('public.law', ['uuid' => $law->uuid]), 
                '0.8', 
                'weekly'
            );
        }
        
        $sitemap .= '</urlset>';
        
        return response($sitemap, 200, [
            'Content-Type' => 'application/xml',
            'Cache-Control' => 'public, max-age=3600'
        ]);
    }

    /**
     * Sitemap dos artigos (dividido por partes para evitar sitemaps muito grandes)
     */
    public function articles()
    {
        $sitemap = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $sitemap .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        
        // Buscar artigos com suas respectivas leis, limitando para evitar sitemap muito grande
        $articles = LawArticle::where('is_active', true)
            ->with('legalReference')
            ->whereHas('legalReference', function($query) {
                $query->where('is_active', true);
            })
            ->orderBy('legal_reference_id')
            ->orderByRaw('CAST(article_reference AS UNSIGNED) ASC')
            ->take(5000) // Limitar a 5000 artigos por sitemap
            ->get();
            
        foreach ($articles as $article) {
            if ($article->legalReference) {
                $sitemap .= $this->addUrl(
                    route('public.article', [
                        'lawUuid' => $article->legalReference->uuid,
                        'articleUuid' => $article->uuid
                    ]), 
                    '0.7', 
                    'monthly'
                );
            }
        }
        
        $sitemap .= '</urlset>';
        
        return response($sitemap, 200, [
            'Content-Type' => 'application/xml',
            'Cache-Control' => 'public, max-age=86400' // Cache por 24h para artigos
        ]);
    }

    /**
     * Helper para adicionar uma URL ao sitemap
     */
    private function addUrl(string $url, string $priority = '0.5', string $changefreq = 'monthly'): string
    {
        $xml = '  <url>' . "\n";
        $xml .= '    <loc>' . htmlspecialchars($url) . '</loc>' . "\n";
        $xml .= '    <lastmod>' . now()->toISOString() . '</lastmod>' . "\n";
        $xml .= '    <changefreq>' . $changefreq . '</changefreq>' . "\n";
        $xml .= '    <priority>' . $priority . '</priority>' . "\n";
        $xml .= '  </url>' . "\n";
        return $xml;
    }
}