<?php

use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->web(append: [
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);
        // "Cancelar inscrição" do Gmail/Outlook chega sem token CSRF; a rota é protegida por assinatura.
        $middleware->validateCsrfTokens(except: ['emails/sair/*']);
        $middleware->alias([
            'admin' => \App\Http\Middleware\AdminMiddleware::class,
            'stripe' => \App\Http\Middleware\StripeWebhookMiddleware::class,
            'study-materials.agent' => \App\Http\Middleware\StudyMaterialAgent::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // As rotas do agente de material de estudo respondem sempre em JSON, mesmo sem o
        // cabeçalho Accept: quem chama é um agente com curl, que precisa ler o erro.
        $exceptions->shouldRenderJsonWhen(
            fn ($request) => $request->is('api/v1/admin/study-materials*') || $request->expectsJson()
        );
    })->create();
