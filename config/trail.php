<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Mapa novo (quadrados de 4 fases)
    |--------------------------------------------------------------------------
    |
    | Quem joga o mapa novo: 'admins' (padrão) ou 'everyone'. Vale para a web
    | (/beta/play) e para o app (/api/v1/trail e `map_version` em /me). Virar
    | para 'everyone' libera para todo mundo sem build nova do app.
    |
    */

    'audience' => env('TRAIL_AUDIENCE', 'admins'),
];
