// Gera o e-mail da Black Friday 2026 no formato do editor do Brevo (YAML) e uma
// prévia em HTML a partir das mesmas linhas, para revisar o visual.
//
//   node docs/black-friday-2026/email/build.mjs      (grava em docs/black-friday-2026/saida)
//
// A oferta é a de config/subscription.php (seasonal_offer): 30% em todos os planos,
// só na primeira cobrança, até quarta, 2/12, 23h59 de Brasília. O
// desconto já aparece aplicado em /subscription, sem cupom. Se mudar a oferta lá,
// mude os números daqui.
//
// Mesmos blocos e helpers do e-mail da nova versão (docs/divulgacao-nova-versao/email).

import { randomUUID } from 'node:crypto';
import { mkdirSync, writeFileSync } from 'node:fs';

const OUT = new URL('../saida/', import.meta.url);
mkdirSync(OUT, { recursive: true });

// ---------------------------------------------------------------- a oferta

const OFFER = {
    percent: 30,
    ends: 'quarta-feira, 2 de dezembro, às 23h59',
    endsShort: 'quarta, 2/12',
    yearly: { from: 'R$ 107,91', to: 'R$ 75,54', note: 'equivale a R$ 6,30 por mês' },
    quarterly: { from: 'R$ 30,57', to: 'R$ 21,40', note: 'equivale a R$ 7,13 por mês' },
    monthly: { from: 'R$ 11,99', to: 'R$ 8,39', note: 'no primeiro mês' },
};

// ---------------------------------------------------------------- cores (as do jogo)

const INK = '#2B2925';
const TEXT = '#47433C';
const MUTED = '#78726A';
const PAPER = '#F4F1EA';
const LINE = '#E4DED2';
const YELLOW = '#EFC12F';
const MARK = '#F3D873';
const PLUS = '#D6438D';
const PLUS_EDGE = '#A8306C';
const PLUS_SOFT = '#FBE6F0';
const FONT = "font-family:'Instrument Sans','Trebuchet MS',Helvetica,Arial,sans-serif;";
const FONT_LIST = "'Instrument Sans','Trebuchet MS',helvetica,sans-serif";

const SIDE = '32px';
const SIDE_MOBILE = '20px';
const APP = 'https://memorizedireito.com';
const UTM = 'utm_source=brevo&utm_medium=email&utm_campaign=black-friday-2026';
const YEARLY_URL = `${APP}/subscription?plan=yearly&${UTM}`;
const QUARTERLY_URL = `${APP}/subscription?plan=quarterly&${UTM}`;
const MONTHLY_URL = `${APP}/subscription?plan=monthly&${UTM}`;

// Ficha preenchida, como as lacunas completas da fase.
const filled = (word) =>
    `<span style="display:inline-block; background-color:#FFFFFF; color:${INK}; padding:0 12px; border-radius:10px; border:2px solid #DCD6CA; border-bottom:4px solid #DCD6CA; line-height:1.3;">${word}</span>`;

// Botão 3D, do jeito dos botões do jogo.
const button = (label, href, bg = PLUS, edge = PLUS_EDGE, color = '#FFFFFF') =>
    `<a href="${href}" style="display:inline-block; background-color:${bg}; color:${color}; ${FONT} font-size:18px; font-weight:700; line-height:1.2; text-decoration:none; padding:16px 30px; border-radius:14px; border-bottom:5px solid ${edge};">${label}</a>`;

const p = (html, style = '') => `<p style="margin:0; ${FONT} ${style}">${html}</p>`;

// Marca-texto atrás da palavra.
const mark = (html) => `<span style="background-color:${MARK}; padding:0 4px; white-space:nowrap;">${html}</span>`;

// Selo arredondado (Black Friday, desconto).
const pill = (label, bg, color) =>
    `<span style="display:inline-block; background-color:${bg}; color:${color}; ${FONT} font-size:14px; font-weight:700; line-height:1.2; padding:7px 14px; border-radius:999px;">${label}</span>`;

// Cartão de um plano: nome e selo, preço riscado, preço novo e o link. O anual vem em
// destaque (borda magenta do Plus); os outros, calmos.
const planCard = ({ name, from, to, note, href, cta, highlight }) =>
    `<div style="background-color:#FFFFFF; border:2px solid ${highlight ? PLUS : LINE}; border-bottom:6px solid ${highlight ? PLUS : LINE}; border-radius:20px; padding:22px 22px 24px;">` +
    `<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;"><tr>` +
    `<td valign="middle">${p(name, `font-size:21px; line-height:1.3; font-weight:700; color:${INK};`)}</td>` +
    `<td valign="middle" align="right">${pill(`-${OFFER.percent}%`, highlight ? PLUS : INK, highlight ? '#FFFFFF' : YELLOW)}</td>` +
    `</tr></table>` +
    p(`de <span style="text-decoration:line-through;">${from}</span>`, `font-size:15px; line-height:1.4; color:${MUTED}; margin-top:12px;`) +
    p(`por ${to}`, `font-size:32px; line-height:1.2; font-weight:700; color:${INK}; margin-top:2px;`) +
    p(note, `font-size:15px; line-height:1.5; color:${TEXT}; margin-top:4px;`) +
    `<p style="margin:18px 0 0 0;">${highlight ? button(cta, href) : button(cta, href, INK, '#000000', '#FFFFFF')}</p>` +
    `</div>`;

// ---------------------------------------------------------------- blocos do Brevo

const nid = () => randomUUID();
const meta = { customBlockId: null, isCustomBlock: null, moduleName: null, sidebarSection: null, 'sync-module-id': null, thumbnail: null };

function text(html, layout = {}) {
    return { ...meta, id: nid(), title: null, type: 'text', layout: { '320px': { width: '100%' }, default: { width: '100%', ...layout } }, content: html };
}

function image({ id = nid(), src, alt, width, mobileWidth, height, align = 'left', radius = '0px', imgSize }) {
    return {
        ...meta,
        id,
        title: null,
        type: 'image',
        layout: {
            '320px': { width: mobileWidth },
            default: { align, 'canvas-height': height, dynamic: false, embed: false, 'img-border-radius': radius, ...(imgSize ? { imgSize } : {}), width },
        },
        content: { alt, src },
    };
}

function column(blocks, padding = ['0px', '0px']) {
    return {
        ...meta,
        id: nid(),
        isEmpty: false,
        title: null,
        type: 'grid-column',
        layout: { '320px': [], default: { 'padding-left': padding[0], 'padding-right': padding[1], valign: 'top' } },
        content: blocks,
    };
}

function row(title, columns, { id = nid(), background, top, bottom, side = SIDE, sideMobile = SIDE_MOBILE, fullWidth = false, dist = '100', gutter = '0px' } = {}) {
    return {
        ...meta,
        dist,
        gutter,
        id,
        title,
        type: 'grid-row',
        layout: {
            '320px': { 'padding-left': sideMobile, 'padding-right': sideMobile },
            default: {
                align: 'center',
                ...(background ? { 'background-color': background } : {}),
                'full-width': fullWidth,
                'padding-bottom': bottom,
                'padding-left': side,
                'padding-right': side,
                'padding-top': top,
                valign: 'top',
                width: '100%',
            },
        },
        content: columns,
    };
}

// ---------------------------------------------------------------- conteúdo

const PERKS = ['Energia e vidas infinitas', 'Cada combo vale +5 XP', 'Todas as legislações', 'Fantasias exclusivas', 'Sem anúncios', 'Garantia de 7 dias'];

// As vantagens em etiquetas, como na PerkList do site.
const perkTags = PERKS.map(
    (perk) =>
        `<span style="display:inline-block; margin:0 6px 8px 0; background-color:${PLUS_SOFT}; color:${PLUS_EDGE}; ${FONT} font-size:15px; font-weight:700; line-height:1.2; padding:8px 14px; border-radius:999px;">${perk}</span>`,
).join('');

const rows = [
    // A logo do e-mail de sempre (mesmos ids e imagem).
    row(
        'Logo',
        [
            {
                ...column([
                    image({
                        id: '1e127454-853c-4089-8403-91b320da5612',
                        src: 'https://img.mailinblue.com/7793425/images/content_library/original/6892d4d3e0117a2b4eeb50bc.png',
                        alt: 'Memorize Direito',
                        width: '170px',
                        mobileWidth: '150px',
                        height: '101px',
                        imgSize: '7366',
                    }),
                ]),
                id: '64248570-0b27-462a-b363-f8b83e26a74b',
            },
        ],
        { id: '5fd2a2e6-1c98-4cb3-bf63-7d9ed605dd83', background: '#FFFFFF', top: '32px', bottom: '8px' },
    ),

    // Destaque: a oferta escrita como um artigo, com a lacuna preenchida.
    row(
        'Hero - Artigo',
        [
            column([
                text(
                    `<p style="margin:0 0 22px 0;">${pill('Black Friday', INK, YELLOW)}</p>` +
                        p(`Art. 1º Fica concedido ${filled(`${OFFER.percent}%`)} de desconto em todos os planos do Plus.`, `font-size:30px; line-height:1.5; font-weight:700; color:${INK};`) +
                        p(
                            `Até ${OFFER.ends}. O anual sai por ${mark(OFFER.yearly.to)}, o que dá R$ 6,30 por mês para estudar a lei sem parar.`,
                            `font-size:17px; line-height:1.6; color:${TEXT}; margin-top:20px;`,
                        ),
                ),
                text(`<p style="margin:0;">${button(`Garantir ${OFFER.percent}% no Plus`, YEARLY_URL)}</p>`, { 'padding-top': '26px' }),
            ]),
        ],
        { background: '#FFFFFF', top: '12px', bottom: '36px' },
    ),

    // A pessoa sendo nomeada: o GIF de tudo que é do Plus no site.
    row(
        'Imagem - Plus',
        [
            column([
                image({
                    src: `${APP}/img/premium-nomeado.gif`,
                    alt: 'Uma pessoa assinando a nomeação',
                    width: '260px',
                    mobileWidth: '220px',
                    height: '259px',
                    radius: '18px',
                    align: 'center',
                }),
            ]),
        ],
        { background: PAPER, top: '36px', bottom: '8px', fullWidth: true },
    ),

    row(
        'Planos - Titulo',
        [
            column([
                text(
                    p(`Escolha o seu ${mark('plano')}`, `font-size:26px; line-height:1.3; font-weight:700; color:${INK}; text-align:center;`) +
                        p('O desconto já aparece aplicado na página, sem cupom.', `font-size:16px; line-height:1.6; color:${TEXT}; margin-top:8px; text-align:center;`),
                ),
            ]),
        ],
        { background: PAPER, top: '20px', bottom: '8px', fullWidth: true },
    ),

    row(
        'Plano anual',
        [column([text(planCard({ name: 'Anual', ...OFFER.yearly, href: YEARLY_URL, cta: 'Quero o anual', highlight: true }))])],
        { background: PAPER, top: '10px', bottom: '10px', fullWidth: true },
    ),

    row(
        'Plano trimestral',
        [column([text(planCard({ name: 'Trimestral', ...OFFER.quarterly, href: QUARTERLY_URL, cta: 'Quero o trimestral', highlight: false }))])],
        { background: PAPER, top: '10px', bottom: '10px', fullWidth: true },
    ),

    row(
        'Plano mensal',
        [column([text(planCard({ name: 'Mensal', ...OFFER.monthly, href: MONTHLY_URL, cta: 'Quero o mensal', highlight: false }))])],
        { background: PAPER, top: '10px', bottom: '10px', fullWidth: true },
    ),

    // A regra da renovação, dita sem letra miúda.
    row(
        'Paragrafo unico',
        [
            column([
                text(
                    p(
                        `<strong style="color:${INK};">Parágrafo único.</strong> O desconto vale na primeira cobrança. Depois, a assinatura renova pelo preço normal, e você pode cancelar quando quiser. Se não gostar, devolvemos o valor em até 7 dias.`,
                        `font-size:15px; line-height:1.6; color:${TEXT};`,
                    ),
                ),
            ]),
        ],
        { background: PAPER, top: '14px', bottom: '40px', fullWidth: true },
    ),

    // O que vem com o Plus.
    row(
        'Vantagens',
        [
            column([
                text(
                    `<p style="margin:0 0 16px 0;">${pill('+ Plus', PLUS, '#FFFFFF')}</p>` +
                        p('Art. 2º Quem assina o Plus joga sem esperar a energia voltar.', `font-size:24px; line-height:1.4; font-weight:700; color:${INK};`) +
                        `<div style="margin-top:18px;">${perkTags}</div>`,
                ),
                text(`<p style="margin:0;">${button('Assinar com desconto', YEARLY_URL)}</p>`, { 'padding-top': '14px' }),
            ]),
        ],
        { background: '#FFFFFF', top: '40px', bottom: '44px', fullWidth: true },
    ),

    // O prazo, por último, em amarelo.
    row(
        'Prazo',
        [
            column([
                text(
                    p(`A oferta termina ${OFFER.endsShort}, às 23h59.`, `font-size:24px; line-height:1.35; font-weight:700; color:${INK};`) +
                        p('Depois disso, o Plus volta ao preço normal.', `font-size:17px; line-height:1.6; color:${INK}; margin-top:8px;`),
                ),
                text(`<p style="margin:0;">${button('Aproveitar agora', YEARLY_URL, INK, '#000000', '#FFFFFF')}</p>`, { 'padding-top': '22px' }),
            ]),
        ],
        { background: YELLOW, top: '40px', bottom: '44px', fullWidth: true },
    ),

    // Rodapé: a logo clara, contato e os links obrigatórios (mesmos ids).
    row(
        'Rodape',
        [
            column([
                image({
                    id: '8b48b42a-29d2-46f4-a6ce-9593d9a42a3e',
                    src: 'https://img.mailinblue.com/7793425/images/content_library/original/689376dde1e72a05477c574b.png',
                    alt: 'Memorize Direito',
                    width: '130px',
                    mobileWidth: '120px',
                    height: '78px',
                    imgSize: '7366',
                }),
                text(
                    p(
                        `<a href="mailto:contato@memorizedireito.com" style="color:#FFFFFF; text-decoration:none;">contato@memorizedireito.com</a>&nbsp;&nbsp;&nbsp;&nbsp;<a href="${APP}" style="color:#FFFFFF; text-decoration:none;">memorizedireito.com</a>&nbsp;&nbsp;&nbsp;&nbsp;<a href="https://www.instagram.com/memorizedireitoapp" style="color:#FFFFFF; text-decoration:none;">Instagram</a>`,
                        'font-size:14px; line-height:1.6; color:#FFFFFF;',
                    ) +
                        p('Você recebeu este e-mail porque tem uma conta no Memorize Direito.', 'font-size:13px; line-height:1.6; color:#B5AD9F; margin-top:14px;') +
                        p(
                            `<a href="${APP}/privacy-policy" style="color:#D8D2C6; text-decoration:underline;">Política de Privacidade</a>&nbsp;&nbsp;&nbsp;&nbsp;<a href="${APP}/terms" style="color:#D8D2C6; text-decoration:underline;">Termos de Uso</a>&nbsp;&nbsp;&nbsp;&nbsp;<a href="{{ unsubscribe }}" style="color:#D8D2C6; text-decoration:underline;">Descadastrar</a>`,
                            'font-size:13px; line-height:1.6; color:#D8D2C6; margin-top:6px;',
                        ),
                    { 'padding-top': '16px' },
                ),
            ]),
        ],
        { id: '8f114261-9587-4782-b59d-eaa2bc6fe1fe', background: '#1B1A17', top: '32px', bottom: '32px', fullWidth: true },
    ),
];

const SUBJECT = 'Black Friday: 30% off em todo o Plus';
const PREVIEW = 'O anual sai por R$ 75,54 (R$ 6,30 por mês) até quarta, 2/12. Sem cupom.';

const email = {
    author: 'Memorize Direito',
    brandUpdateTime: '2025-03-22T10:00:00.000+02:00',
    brevo_settings: {
        category: 'marketing',
        sender_domain: 'memorizedireito.com',
        tags: ['black-friday-2026', 'plus'],
        template_id: 'memorize_direito_black_friday_2026',
    },
    campaignType: 'classic',
    fields: [],
    fieldsHistory: [],
    firstLoadSocialIcons: false,
    fonts: ['https://fonts.googleapis.com/css2?family=Instrument+Sans:wght@400;700&display=swap'],
    from_email: 'noreply@memorizedireito.com',
    from_name: 'Memorize Direito',
    hideSwapBanner: false,
    id: nid(),
    name: 'memorize-direito-black-friday-2026',
    subject: SUBJECT,
    ui: { showDeleteOption: false, showMoreOption: false },
    'user-hash': 'c954697b8131a8b8a6eaea560867915c',
    version: '3.0.1',
    body: {
        id: nid(),
        type: 'body',
        layout: { '320px': [], default: { 'background-color': '#FFFFFF', direction: 'ltr', 'wrapper-width': '600px' } },
        content: [
            {
                'content-type': 'wrapper',
                ...meta,
                id: nid(),
                title: null,
                type: 'rows',
                layout: { '320px': [], default: { align: 'center', valign: 'top' } },
                content: rows,
            },
        ],
    },
    media: { '320px': { 'max-width': 600, 'min-width': 0 } },
    styles: {
        'compact-text': { properties: { color: TEXT, 'font-family': FONT_LIST, 'font-size': '15px', 'line-height': '1.4' }, title: 'Compact Text' },
        default: { properties: { color: TEXT, 'font-family': FONT_LIST, 'font-size': '17px', 'line-height': '1.6' }, title: 'Paragraph' },
        'default-button': {
            properties: {
                'background-color': PLUS,
                'border-radius': '14px',
                'border-width': '0px',
                color: '#FFFFFF',
                'font-family': FONT_LIST,
                'font-size': '18px',
                'font-weight': 700,
                'remove-vml-button': 'optional',
                width: '50%',
            },
            title: 'Button',
        },
        'default-heading1': { properties: { color: INK, 'font-family': FONT_LIST, 'font-size': '30px' }, title: 'Heading 1' },
        'default-heading2': { properties: { color: INK, 'font-family': FONT_LIST, 'font-size': '24px' }, title: 'Heading 2' },
    },
};

// ---------------------------------------------------------------- YAML

// No mesmo estilo do export do Brevo: item de lista num "-" sozinho, com as
// chaves logo abaixo; objeto vazio vira [].
const scalar = (v) => {
    if (v === null) return 'null';
    if (typeof v === 'boolean' || typeof v === 'number') return String(v);
    return `'${String(v).replace(/'/g, "''")}'`;
};

function yaml(value, indent = 0) {
    const pad = ' '.repeat(indent);
    const lines = [];
    for (const [key, v] of Object.entries(value)) {
        const isEmpty = v !== null && typeof v === 'object' && Object.keys(v).length === 0;
        if (isEmpty) lines.push(`${pad}${key}: []`);
        else if (Array.isArray(v)) {
            lines.push(`${pad}${key}:`);
            for (const item of v) {
                if (item !== null && typeof item === 'object') lines.push(`${pad}  -`, yaml(item, indent + 4));
                else lines.push(`${pad}  - ${scalar(item)}`);
            }
        } else if (v !== null && typeof v === 'object') lines.push(`${pad}${key}:`, yaml(v, indent + 2));
        else lines.push(`${pad}${key}: ${scalar(v)}`);
    }
    return lines.join('\n');
}

writeFileSync(new URL('memorize-direito-black-friday-2026.yaml', OUT), yaml(email) + '\n');

// ---------------------------------------------------------------- prévia

// Aproximação da renderização do Brevo: cada linha é uma faixa (larga quando
// full-width), com as colunas lado a lado dentro dos 600px. No topo, como a caixa
// de entrada mostraria: remetente, assunto e pré-visualização.
function renderBlock(block) {
    const l = block.layout.default;
    if (block.type === 'text') return `<div style="padding-top:${l['padding-top'] ?? '0px'}; padding-bottom:${l['padding-bottom'] ?? '0px'};">${block.content}</div>`;
    return `<div style="text-align:${l.align};"><img src="${block.content.src}" alt="${block.content.alt}" width="${parseInt(l.width)}" style="display:inline-block; max-width:100%; height:auto; border-radius:${l['img-border-radius']};"></div>`;
}
function renderRow(r) {
    const l = r.layout.default;
    const weights = r.dist === '100' ? [12] : r.dist.split('-').map(Number);
    const cols = r.content
        .map((c, i) => `<td valign="top" width="${Math.round((100 * weights[i]) / 12)}%" style="padding-left:${c.layout.default['padding-left']}; padding-right:${c.layout.default['padding-right']};">${c.content.map(renderBlock).join('')}</td>`)
        .join('');
    const inner = `<table role="presentation" width="600" cellpadding="0" cellspacing="0" style="width:600px; max-width:100%; margin:0 auto; background-color:${l['background-color'] ?? 'transparent'};"><tr><td style="padding:${l['padding-top']} ${l['padding-right']} ${l['padding-bottom']} ${l['padding-left']};"><table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr>${cols}</tr></table></td></tr></table>`;
    return `<div style="background-color:${l['full-width'] ? l['background-color'] : 'transparent'};">${inner}</div>`;
}
const inbox =
    `<div style="max-width:600px; margin:0 auto; padding:16px 20px; border-bottom:1px solid ${LINE}; ${FONT}">` +
    `<div style="font-size:15px; font-weight:700; color:${INK};">Memorize Direito</div>` +
    `<div style="font-size:15px; font-weight:700; color:${INK}; margin-top:2px;">${SUBJECT}</div>` +
    `<div style="font-size:14px; color:${MUTED}; margin-top:2px;">${PREVIEW}</div></div>`;
writeFileSync(
    new URL('previa.html', OUT),
    `<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Prévia: e-mail da Black Friday</title>` +
        `<link rel="stylesheet" href="${email.fonts[0]}"><style>body{margin:0;background:#fff} td p{margin:0}</style></head><body>` +
        inbox +
        rows.map(renderRow).join('') +
        `</body></html>`,
);

console.log('ok:', rows.length, 'linhas;', 'assunto', SUBJECT.length, 'caracteres; pré-visualização', PREVIEW.length, 'caracteres');
