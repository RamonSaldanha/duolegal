<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title inertia>{{ config('app.name', 'Laravel') }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />

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

        <script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=ca-pub-2585274176504938"
        crossorigin="anonymous"></script>

        @routes
        @vite(['resources/js/app.ts'])
        @inertiaHead

        <!-- Meta Pixel Code -->
        <script>
        !function(f,b,e,v,n,t,s)
        {if(f.fbq)return;n=f.fbq=function(){n.callMethod?
        n.callMethod.apply(n,arguments):n.queue.push(arguments)};
        if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';
        n.queue=[];t=b.createElement(e);t.async=!0;
        t.src=v;s=b.getElementsByTagName(e)[0];
        s.parentNode.insertBefore(t,s)}(window, document,'script',
        'https://connect.facebook.net/en_US/fbevents.js');
        fbq('init', '2107356850051241');
        fbq('track', 'PageView');
        </script>
        <noscript><img height="1" width="1" style="display:none"
        src="https://www.facebook.com/tr?id=2107356850051241&ev=PageView&noscript=1"
        /></noscript>
        <!-- End Meta Pixel Code -->
    </head>
    <body class="font-sans antialiased">
        @inertia
    </body>
</html>
