<?php

/*
|--------------------------------------------------------------------------
| Opções do criador de avatar
|--------------------------------------------------------------------------
|
| Lista de ids aceitos por grupo. Serve para validar o que chega do navegador
| em AvatarUpdateRequest — nenhum valor fora daqui é gravado.
|
| Cada id corresponde a um desenho em resources/js/lib/avatar/parts/. Ao
| acrescentar uma peça nova, atualize os dois lados: a lista aqui e o catálogo
| em resources/js/lib/avatar/options.ts.
|
| As cores não têm lista fixa de propósito: o servidor só exige o formato
| `#rrggbb`. Assim dá para mexer nas paletas da interface sem mexer no backend.
|
*/

return [
    'shapes' => [
        'body' => ['magro', 'gordo'],
        'hair' => ['nenhum', 'comprido'],
        'brows' => ['reta', 'arqueada'],
    ],

    'colors' => [
        'skin',
        // Cabelo e sobrancelha; a barba vai usar a mesma cor.
        'hairColor',
        'clothesColor',
        'background',
    ],
];
