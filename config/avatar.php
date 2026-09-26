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
| Atenção: toda chave de forma daqui vira regra `required`. Um grupo novo só
| pode entrar junto com DEFAULT_AVATAR, SHAPE_IDS e randomAvatarConfig() do
| lado do cliente, senão o editor passa a mandar payload incompleto.
|
| As cores não têm lista fixa de propósito: o servidor só exige o formato
| `#rrggbb`. Assim dá para mexer nas paletas da interface sem mexer no backend.
|
| `premium` lista, por grupo, os ids exclusivos de assinante (plano `default`
| ativo). Quem não assina não consegue salvá-los, e quem deixou de assinar para
| de exibi-los — ver User::publicAvatarConfig(). O primeiro id de cada grupo
| nunca pode ser premium: é para ele que a peça volta.
|
*/

return [
    'shapes' => [
        'body' => ['magro', 'gordo'],
        'hair' => ['nenhum', 'calvo', 'curto', 'afro', 'chanel', 'comprido', 'liso', 'coque'],
        // `cilios` desenha a sobrancelha e os cílios na mesma opção.
        'brows' => ['reta', 'arqueada', 'cilios'],
        'mouth' => ['sorriso', 'aberta'],
        'earrings' => ['nenhum', 'argola', 'simples'],
        'glasses' => ['nenhum', 'grau', 'sol'],
        // Sem cor própria: a barba usa `hairColor`.
        'beard' => ['nenhum', 'bigode', 'grossa'],
        // Fantasia: cobre o corpo inteiro no lugar da roupa (a de astronauta,
        // também a cabeça).
        'outfit' => ['nenhum', 'toga', 'astronauta'],
    ],

    'premium' => [
        'outfit' => ['toga', 'astronauta'],
    ],

    'colors' => [
        'skin',
        // Cabelo, sobrancelha, cílio e barba.
        'hairColor',
        'clothesColor',
        'earringColor',
        // No óculos de grau pinta o aro; no de sol pinta a lente.
        'glassesColor',
        'background',
    ],
];
