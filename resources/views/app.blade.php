<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title inertia>{{ config('app.name', 'Laravel') }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />

        <!-- Google AdSense -->
        <script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=ca-pub-2585274176504938"
            crossorigin="anonymous"></script>

        <!-- Stripe.js - Carregado antes de qualquer outro script -->
        <script src="https://js.stripe.com/v3/" id="stripe-js"></script>
        <script>
            // Verifica se o Stripe foi carregado corretamente
            window.addEventListener('DOMContentLoaded', function() {
                if (typeof Stripe === 'undefined') {
                    console.error('Stripe.js não foi carregado corretamente!');
                    // Tenta carregar novamente
                    var script = document.createElement('script');
                    script.src = 'https://js.stripe.com/v3/';
                    script.async = true;
                    script.onload = function() {
                        console.log('Stripe.js recarregado com sucesso!');
                    };
                    document.head.appendChild(script);
                } else {
                    console.log('Stripe.js carregado com sucesso!');
                }
            });
        </script>

        @routes
        @vite(['resources/js/app.ts'])
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        @inertia
    </body>
</html>
