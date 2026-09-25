/**
 * Galeria de avatares: desenha todas as peças e cores do catálogo numa página só.
 *
 *   npm run avatar:gallery
 *
 * Gera `public/avatar-gallery.html` (fora do git), que abre com o Laravel rodando
 * em http://localhost:8000/avatar-gallery.html — sem login.
 *
 * Lê `resources/js/lib/avatar` direto do código-fonte pelo Vite, então a galeria
 * sempre mostra o catálogo atual: opção nova num grupo que já existe aparece aqui
 * sozinha; grupo novo precisa entrar na lista `shapes` de `page()`.
 * Por isso não existe bundle versionado da lib — ele ficaria desatualizado.
 */

import { writeFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { createServer } from 'vite';

const root = fileURLToPath(new URL('..', import.meta.url));
const output = fileURLToPath(new URL('../public/avatar-gallery.html', import.meta.url));

// `configFile: false` de propósito: a lib só tem imports relativos e não precisa
// dos plugins do projeto (o do Laravel escreveria o `public/hot`).
const vite = await createServer({ root, configFile: false, logLevel: 'error', server: { middlewareMode: true }, appType: 'custom' });

try {
    const A = await vite.ssrLoadModule('/resources/js/lib/avatar/index.ts');
    writeFileSync(output, page(A));
    console.log(`Galeria gerada em public/avatar-gallery.html`);
    console.log(`Abra http://localhost:8000/avatar-gallery.html com o Laravel rodando.`);
} finally {
    await vite.close();
}

function page(A) {
    const base = A.DEFAULT_AVATAR;

    const tile = (label, cfg) => `<figure><div class="art">${A.buildAvatarSvg(cfg)}</div><figcaption>${label}</figcaption></figure>`;
    const section = (title, tiles, small = false) =>
        `<section><h3>${title}</h3><div class="row${small ? ' small' : ''}">${tiles.join('')}</div></section>`;

    const shapes = [
        ['Corpo', 'body', A.BODIES_OPTIONS],
        ['Cabelo', 'hair', A.HAIRS],
        ['Sobrancelha', 'brows', A.BROWS],
        ['Boca', 'mouth', A.MOUTHS],
        ['Brinco', 'earrings', A.EARRINGS],
        ['Óculos', 'glasses', A.GLASSES],
        ['Barba', 'beard', A.BEARDS],
    ];

    const colors = [
        ['Tom da pele', 'skin', A.SKIN_COLORS],
        ['Cor do cabelo e da sobrancelha', 'hairColor', A.HAIR_COLORS],
        ['Cor da roupa', 'clothesColor', A.CLOTHES_COLORS],
        ['Cor do brinco', 'earringColor', A.EARRING_COLORS, { earrings: 'argola' }],
        ['Cor dos óculos (de sol: pinta a lente)', 'glassesColor', A.GLASSES_COLORS, { glasses: 'sol' }],
        ['Cor do fundo', 'background', A.BACKGROUND_COLORS],
    ];

    let body = '<h2>Formas</h2>';
    for (const [title, key, options, extra = {}] of shapes) {
        body += section(
            `${title} <code>${key}</code>`,
            options.map((o) => tile(`${o.label} <code>${o.id}</code>`, { ...base, ...extra, [key]: o.id })),
        );
    }

    body += '<h2>Paletas</h2>';
    for (const [title, key, list, extra = {}] of colors) {
        body += section(
            `${title} <code>${key}</code> · ${list.length} cores`,
            list.map((c) => tile(c, { ...base, ...extra, [key]: c })),
            true,
        );
    }

    body += '<h2>Combinações</h2>';
    body += section(
        'Óculos × brinco',
        A.GLASSES.flatMap((g) => A.EARRINGS.map((e) => tile(`${g.id} + ${e.id}`, { ...base, glasses: g.id, earrings: e.id }))),
    );
    body += section(
        'Sorteios (<code>randomAvatarConfig</code>)',
        Array.from({ length: 20 }, (_, i) => tile(`#${i + 1}`, A.randomAvatarConfig())),
        true,
    );

    return `<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Galeria de avatares</title>
<style>
:root{--bg:#fafaf9;--fg:#1c1917;--muted:#78716c;--card:#fff;--line:#e7e5e4}
@media (prefers-color-scheme:dark){:root{--bg:#1c1917;--fg:#f5f5f4;--muted:#a8a29e;--card:#292524;--line:#44403c}}
body{margin:0;padding:24px 16px;background:var(--bg);color:var(--fg);font:14px/1.4 system-ui,sans-serif}
h1{margin:0 0 4px}p{margin:0 0 24px;color:var(--muted)}
h2{margin:32px 0 8px;border-bottom:1px solid var(--line);padding-bottom:6px}
h3{font-size:14px;margin:18px 0 8px}code{font-size:12px;color:var(--muted)}
.row{display:grid;grid-template-columns:repeat(auto-fill,minmax(130px,1fr));gap:12px}
.row.small{grid-template-columns:repeat(auto-fill,minmax(96px,1fr))}
figure{margin:0;background:var(--card);border:1px solid var(--line);border-radius:14px;padding:8px}
.art{border-radius:10px;overflow:hidden;aspect-ratio:1}.art svg{display:block;width:100%;height:100%}
figcaption{margin-top:6px;font-size:12px;text-align:center}
</style>
</head>
<body>
<h1>Galeria de avatares</h1>
<p>Cada variação parte do <code>DEFAULT_AVATAR</code> e troca uma peça só. Gerado por <code>npm run avatar:gallery</code>.</p>
${body}
</body>
</html>
`;
}
