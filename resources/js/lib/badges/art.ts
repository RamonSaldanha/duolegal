// @ts-nocheck
/* eslint-disable */
// Copiado do Ateliê de Badges (artefato no claude.ai), estilo chapado.
// Mude a arte lá e copie de novo; aqui só os exports são próprios deste arquivo.
import { FONT_B64 } from './font';

/* ---------- Paletas ---------- */
const SHIELD_SWATCHES = [
  ["Verde-água", "#2E9D94"], ["Azul", "#4C7BE8"], ["Roxo", "#9B5CF6"], ["Terracota", "#E0805F"],
  ["Manteiga", "#F0DFA0"], ["Vermelho", "#E8505B"], ["Verde", "#3FB06A"], ["Marinho", "#34306A"]
];
const RIBBON_SWATCHES = [
  ["Roxo", "#9B5CF6"], ["Verde-água", "#2E9D94"], ["Terracota", "#E0805F"], ["Manteiga", "#F0DFA0"],
  ["Vermelho", "#E8505B"], ["Azul", "#4C7BE8"], ["Verde", "#3FB06A"], ["Marinho", "#34306A"]
];

/* ---------- Cor ---------- */
function hexToHsl(hex) {
  let h = hex.replace("#", "");
  if (h.length === 3) h = h.split("").map(c => c + c).join("");
  const r = parseInt(h.slice(0, 2), 16) / 255, g = parseInt(h.slice(2, 4), 16) / 255, b = parseInt(h.slice(4, 6), 16) / 255;
  const max = Math.max(r, g, b), min = Math.min(r, g, b);
  let hh = 0, s = 0; const l = (max + min) / 2;
  if (max !== min) {
    const d = max - min;
    s = l > .5 ? d / (2 - max - min) : d / (max + min);
    if (max === r) hh = (g - b) / d + (g < b ? 6 : 0);
    else if (max === g) hh = (b - r) / d + 2;
    else hh = (r - g) / d + 4;
    hh /= 6;
  }
  return [hh * 360, s, l];
}
function hslToHex(h, s, l) {
  h = ((h % 360) + 360) % 360 / 360;
  const f = n => {
    const k = (n + h * 12) % 12, a = s * Math.min(l, 1 - l);
    const c = l - a * Math.max(-1, Math.min(k - 3, 9 - k, 1));
    return Math.round(c * 255).toString(16).padStart(2, "0");
  };
  return "#" + f(0) + f(8) + f(4);
}
const clamp = (v, a, b) => Math.max(a, Math.min(b, v));
function adj(hex, dl, ds = 0, dh = 0) {
  const [h, s, l] = hexToHsl(hex);
  return hslToHex(h + dh, clamp(s + ds, 0, 1), clamp(l + dl, 0.05, 0.97));
}
function validHex(v, fb) { return /^#[0-9a-f]{6}$/i.test(v || "") ? v : fb; }

/* ---------- Geometria ---------- */
function hexPts(cx, cy, r) {
  const p = [];
  for (let k = 0; k < 6; k++) { const a = (-90 + 60 * k) * Math.PI / 180; p.push([cx + r * Math.cos(a), cy + r * Math.sin(a)]); }
  return p;
}
function roundPoly(pts, r) {
  const n = pts.length, f = v => v.toFixed(2); let d = "";
  for (let i = 0; i < n; i++) {
    const p0 = pts[(i - 1 + n) % n], p1 = pts[i], p2 = pts[(i + 1) % n];
    const l1 = Math.hypot(p0[0] - p1[0], p0[1] - p1[1]), l2 = Math.hypot(p2[0] - p1[0], p2[1] - p1[1]);
    const a = [p1[0] + (p0[0] - p1[0]) * r / l1, p1[1] + (p0[1] - p1[1]) * r / l1];
    const b = [p1[0] + (p2[0] - p1[0]) * r / l2, p1[1] + (p2[1] - p1[1]) * r / l2];
    d += (i === 0 ? "M" : "L") + f(a[0]) + "," + f(a[1]) + " Q" + f(p1[0]) + "," + f(p1[1]) + " " + f(b[0]) + "," + f(b[1]) + " ";
  }
  return d + "Z";
}
const esc = s => String(s).replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;");
function sparkle(x, y, r, op) {
  return `<path d="M${x},${y - r} Q${x},${y} ${x + r},${y} Q${x},${y} ${x},${y + r} Q${x},${y} ${x - r},${y} Q${x},${y} ${x},${y - r} Z" fill="#fff" opacity="${op}"/>`;
}

/* ---------- Ilustrações ---------- */
const STEEL_DARK = "#454c64";
function cuffRing(u, cx) {
  const r = 15.5, P = 2 * Math.PI * (r + 1.9);
  return `<g>
    <circle cx="${cx}" cy="0" r="${r}" fill="none" stroke="${STEEL_DARK}" stroke-width="11"/>
    <circle cx="${cx}" cy="0" r="${r}" fill="none" stroke="url(#${u}-st)" stroke-width="7.4"/>
    <circle cx="${cx}" cy="0" r="${r - 2.4}" fill="none" stroke="#6f7891" stroke-width="1.3" opacity=".75"/>
    <circle cx="${cx}" cy="0" r="${r + 1.9}" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-dasharray="0 ${(P * 0.45).toFixed(1)} ${(P * 0.22).toFixed(1)} ${P.toFixed(1)}" opacity=".95"/>
  </g>`;
}
function cuffHousing(u, cx) {
  return `<g transform="translate(${cx} 0)">
    <rect x="-7.5" y="-9.5" width="15" height="19" rx="3.8" fill="url(#${u}-st)" stroke="${STEEL_DARK}" stroke-width="2"/>
    <rect x="-5" y="-7.4" width="10" height="2.4" rx="1.2" fill="#fff" opacity=".85"/>
    <circle cx="0" cy="1.6" r="1.9" fill="${STEEL_DARK}"/>
    <rect x="-.7" y="2.4" width="1.4" height="3.2" rx=".7" fill="${STEEL_DARK}"/>
  </g>`;
}
function cuffLink(u, cx, rx, ry) {
  return `<ellipse cx="${cx}" cy="0" rx="${rx}" ry="${ry}" fill="none" stroke="${STEEL_DARK}" stroke-width="4.6"/>
    <ellipse cx="${cx}" cy="0" rx="${rx}" ry="${ry}" fill="none" stroke="url(#${u}-st)" stroke-width="2.4"/>`;
}
function handcuffs(u) {
  return `<g transform="translate(127 72) rotate(-28)">
    ${cuffRing(u, -40)}${cuffRing(u, 40)}
    ${cuffLink(u, -3.6, 4.8, 3)}${cuffLink(u, 3.6, 4.8, 1.4)}
    ${cuffLink(u, -9.6, 3, 3)}${cuffLink(u, 9.6, 3, 3)}
    ${cuffHousing(u, -21)}${cuffHousing(u, 21)}
  </g>`;
}

function illusPenal(u) {
  // Código Penal fechado + algemas (uma argola deitada na capa, outra saindo do brasão)
  return `<defs>
    <linearGradient id="${u}-cov" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#ec4458"/><stop offset=".55" stop-color="#c8233a"/><stop offset="1" stop-color="#9c1428"/></linearGradient>
    <linearGradient id="${u}-pg" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#fffbf1"/><stop offset="1" stop-color="#efdcb2"/></linearGradient>
    <linearGradient id="${u}-st" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#ffffff"/><stop offset=".4" stop-color="#d3d9e6"/><stop offset="1" stop-color="#848da4"/></linearGradient>
    <linearGradient id="${u}-gd" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#ffe7a0"/><stop offset="1" stop-color="#e3a923"/></linearGradient>
  </defs>
  <g filter="url(#${u}-ds)">
    <polygon points="56,100 148,72 190,104 190,119 98,147 56,115" fill="#6f0f1d" stroke="#6f0f1d" stroke-width="4" stroke-linejoin="round"/>
    <polygon points="100,132.5 187,106 187,117 100,143.5" fill="url(#${u}-pg)"/>
    <line x1="101" y1="136.2" x2="186" y2="110.3" stroke="#e0c995" stroke-width="1"/>
    <line x1="101" y1="139.8" x2="186" y2="113.8" stroke="#e0c995" stroke-width="1"/>
    <polygon points="56,100 148,72 190,104 98,132" fill="url(#${u}-cov)" stroke="#b81d33" stroke-width="4" stroke-linejoin="round"/>
    <line x1="59" y1="98.6" x2="146" y2="72.4" stroke="#ff9aa6" stroke-width="2.2" stroke-linecap="round" opacity=".75"/>
    <polygon points="69.4,100.4 143,78 176.6,103.6 103,126" fill="none" stroke="url(#${u}-gd)" stroke-width="1.8" stroke-linejoin="round" opacity=".9"/>
    <g transform="translate(150 101) rotate(-17) skewX(-12) scale(1 .78)">
      <text x="0" y="0" text-anchor="middle" dy=".36em" font-family="Fredoka, sans-serif" font-weight="600" font-size="30" fill="#7a0f1f" opacity=".55" transform="translate(1.2 1.6)">§</text>
      <text x="0" y="0" text-anchor="middle" dy=".36em" font-family="Fredoka, sans-serif" font-weight="600" font-size="30" fill="url(#${u}-gd)">§</text>
    </g>
    ${handcuffs(u)}
  </g>`;
}

function illusConst(u) {
  // Brasília: Congresso Nacional (torres, cúpulas da Câmara e do Senado) + bandeira da Praça dos Três Poderes + CF/88
  const MO = "#8796bf";
  const tower = x => `<g>
      <rect x="${x}" y="20" width="14" height="80" rx="1.6" fill="url(#${u}-tw)" stroke="${MO}" stroke-width="1.5"/>
      <g stroke="#a9c6ee" stroke-width="1.1">${Array.from({ length: 12 }, (_, i) => `<line x1="${x + 2.5}" y1="${26 + i * 6}" x2="${x + 11.5}" y2="${26 + i * 6}"/>`).join("")}</g>
      <line x1="${x + 1.8}" y1="22" x2="${x + 1.8}" y2="98" stroke="#fff" stroke-width="1.6" stroke-linecap="round"/>
    </g>`;
  return `<defs>
    <linearGradient id="${u}-mb" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#ffffff"/><stop offset="1" stop-color="#d6deef"/></linearGradient>
    <linearGradient id="${u}-tw" x1="0" y1="0" x2="1" y2="0"><stop offset="0" stop-color="#ffffff"/><stop offset=".6" stop-color="#e6ecf7"/><stop offset="1" stop-color="#bfcae3"/></linearGradient>
    <linearGradient id="${u}-lw" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#e9eef8"/><stop offset="1" stop-color="#cdd6ea"/></linearGradient>
    <radialGradient id="${u}-dm" cx=".35" cy=".3" r=".9"><stop offset="0" stop-color="#ffffff"/><stop offset=".6" stop-color="#e2e8f5"/><stop offset="1" stop-color="#b7c3df"/></radialGradient>
    <linearGradient id="${u}-bw" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#ffffff"/><stop offset="1" stop-color="#c3cde6"/></linearGradient>
    <linearGradient id="${u}-gd" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#fff0b0"/><stop offset="1" stop-color="#e2a51c"/></linearGradient>
    <linearGradient id="${u}-bk" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#3ed27a"/><stop offset=".55" stop-color="#17a356"/><stop offset="1" stop-color="#0b7a3e"/></linearGradient>
    <linearGradient id="${u}-pg" x1="0" y1="0" x2="1" y2="0"><stop offset="0" stop-color="#fffaf0"/><stop offset="1" stop-color="#ead8b0"/></linearGradient>
    <linearGradient id="${u}-pl" x1="0" y1="0" x2="1" y2="0"><stop offset="0" stop-color="#ffffff"/><stop offset="1" stop-color="#9aa6c4"/></linearGradient>
  </defs>
  <g filter="url(#${u}-ds)">
    <rect x="195" y="24" width="3.6" height="96" rx="1.8" fill="url(#${u}-pl)" stroke="${MO}" stroke-width="1"/>
    <circle cx="196.8" cy="23" r="3" fill="url(#${u}-gd)" stroke="#c98d12" stroke-width=".8"/>
    <g transform="translate(198.6 27)">
      <path d="M0,0 Q9,-4 18,0 T36,0 L36,23 Q27,19 18,23 T0,23 Z" fill="#1fa855" stroke="#13853f" stroke-width="1.3" stroke-linejoin="round"/>
      <path d="M18,3.4 L32,11.5 L18,19.6 L4,11.5 Z" fill="#ffd21f"/>
      <circle cx="18" cy="11.5" r="4.9" fill="#2647a8"/>
      <path d="M13.4,10.6 Q18,9 22.6,12.2" stroke="#fff" stroke-width="1" fill="none"/>
      <path d="M1.5,2.4 Q9,-1.2 17,2" stroke="#7fe0a5" stroke-width="1.3" fill="none" stroke-linecap="round" opacity=".9"/>
    </g>
  </g>
  <g filter="url(#${u}-ds)">
    ${tower(103)}${tower(123)}
    <rect x="116" y="46" width="8" height="9" fill="url(#${u}-tw)" stroke="${MO}" stroke-width="1.3"/>
    <rect x="46" y="118" width="148" height="15" rx="2" fill="url(#${u}-lw)" stroke="${MO}" stroke-width="1.5"/>
    <rect x="46" y="118" width="148" height="4" fill="#a7b3d1" opacity=".55"/>
    <g fill="#b9c4de">${Array.from({ length: 11 }, (_, i) => `<rect x="${54 + i * 12.6}" y="123.5" width="7" height="6.5" rx="1.2"/>`).join("")}</g>
    <rect x="36" y="100" width="168" height="20" rx="3" fill="url(#${u}-mb)" stroke="${MO}" stroke-width="1.6"/>
    <path d="M39,103.6 L201,103.6" stroke="#fff" stroke-width="2" stroke-linecap="round"/>
    <path d="M47,100.5 A27,22 0 0 1 101,100.5 Z" fill="url(#${u}-dm)" stroke="${MO}" stroke-width="1.6" stroke-linejoin="round"/>
    <path d="M55,94 A20,15 0 0 1 70,83.5" fill="none" stroke="#fff" stroke-width="2.2" stroke-linecap="round" opacity=".95"/>
    <path d="M138,78 Q140,100.5 166,100.5 Q192,100.5 194,78 Z" fill="url(#${u}-bw)" stroke="${MO}" stroke-width="1.6" stroke-linejoin="round"/>
    <ellipse cx="166" cy="78" rx="28" ry="5.6" fill="#c4cee7" stroke="${MO}" stroke-width="1.6"/>
    <ellipse cx="166" cy="78.6" rx="24" ry="3.6" fill="#aab7d8"/>
    <path d="M143,84 Q147,96 160,98.5" fill="none" stroke="#fff" stroke-width="2.2" stroke-linecap="round" opacity=".95"/>
  </g>
  <g filter="url(#${u}-ds)" transform="translate(62 115) rotate(-12) scale(.55)">
    <rect x="-23" y="-37" width="52" height="72" rx="5" fill="#075a2c"/>
    <rect x="-22" y="-34" width="48" height="66" rx="2" fill="url(#${u}-pg)"/>
    <line x1="22" y1="-31" x2="22" y2="29" stroke="#dcc79a" stroke-width="1"/>
    <line x1="18.5" y1="-32" x2="18.5" y2="30" stroke="#dcc79a" stroke-width="1"/>
    <rect x="-30" y="-38" width="48" height="72" rx="5" fill="url(#${u}-bk)" stroke="#0b7a3e" stroke-width="1.6"/>
    <rect x="-30" y="-38" width="8" height="72" rx="4" fill="#0e8a45"/>
    <line x1="-21.5" y1="-36" x2="-21.5" y2="32" stroke="#5fe39a" stroke-width="1.2" opacity=".7"/>
    <rect x="-17" y="-31" width="30" height="58" rx="3" fill="none" stroke="url(#${u}-gd)" stroke-width="1.6"/>
    <path d="${starPath(-2, -17, 6)}" fill="url(#${u}-gd)" stroke="#c98d12" stroke-width=".8" stroke-linejoin="round"/>
    <text x="-2" y="2" text-anchor="middle" dy=".36em" font-family="Fredoka, sans-serif" font-weight="600" font-size="19" fill="#06572a" opacity=".6" transform="translate(.8 1.2)">CF</text>
    <text x="-2" y="2" text-anchor="middle" dy=".36em" font-family="Fredoka, sans-serif" font-weight="600" font-size="19" fill="url(#${u}-gd)">CF</text>
    <text x="-2" y="18" text-anchor="middle" dy=".36em" font-family="Fredoka, sans-serif" font-weight="600" font-size="8.5" letter-spacing=".6" fill="#ffe08a">1988</text>
    <path d="M-27,-35 L14,-35" stroke="#9af0bf" stroke-width="2" stroke-linecap="round" opacity=".8"/>
  </g>`;
}

function illusConsumidor(u) {
  // Sacola de compras + etiqueta de preço + escudo de proteção (CDC)
  const shield = "M0,-29 Q14,-23 27,-25 L27,1 Q27,21 0,31 Q-27,21 -27,1 L-27,-25 Q-14,-23 0,-29 Z";
  return `<defs>
    <linearGradient id="${u}-bag" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#ffb066"/><stop offset=".5" stop-color="#ff8a2e"/><stop offset="1" stop-color="#e2620e"/></linearGradient>
    <linearGradient id="${u}-sd" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#d4570a"/><stop offset="1" stop-color="#b54406"/></linearGradient>
    <linearGradient id="${u}-sh" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#5aa0ff"/><stop offset=".55" stop-color="#2d6be0"/><stop offset="1" stop-color="#1c48b0"/></linearGradient>
    <linearGradient id="${u}-gd" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#fff0b0"/><stop offset="1" stop-color="#e2a51c"/></linearGradient>
    <linearGradient id="${u}-tg" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#fff3a6"/><stop offset="1" stop-color="#ffc21a"/></linearGradient>
  </defs>
  <g filter="url(#${u}-ds)">
    <path d="M88,59 C88,24 100,22 106,22 C114,22 124,26 124,59" fill="none" stroke="#7a2e05" stroke-width="6.5" stroke-linecap="round"/>
    <path d="M88,59 C88,24 100,22 106,22 C114,22 124,26 124,59" fill="none" stroke="#a8480f" stroke-width="3.2" stroke-linecap="round"/>
    <path d="M60,64 L80,57 L148,57 L128,64 Z" fill="#8e3404" stroke="#7a2e05" stroke-width="1.6" stroke-linejoin="round"/>
    <path d="M128,64 L148,57 L148,139 L128,144 Z" fill="url(#${u}-sd)" stroke="#a63e05" stroke-width="1.6" stroke-linejoin="round"/>
    <path d="M128,64 L138,74 L148,57 M138,74 L138,141" fill="none" stroke="#933604" stroke-width="1.4" stroke-linejoin="round"/>
    <rect x="60" y="64" width="68" height="80" rx="3" fill="url(#${u}-bag)" stroke="#c4520a" stroke-width="1.8"/>
    <path d="M60.8,64.6 L127.2,64.6" stroke="#ffd2a3" stroke-width="2" stroke-linecap="round"/>
    <path d="M64.5,70 L64.5,138" stroke="#ffc896" stroke-width="2.2" stroke-linecap="round" opacity=".8"/>
    <g transform="translate(94 104)" fill="none" stroke="#fff" stroke-width="3.4" stroke-linecap="round" stroke-linejoin="round" opacity=".95">
      <path d="M-17,-13 L-11,-13 L-6,6 L13,6 L17,-7 L-9,-7"/>
      <circle cx="-3" cy="13" r="2.6" fill="#fff" stroke="none"/><circle cx="10" cy="13" r="2.6" fill="#fff" stroke="none"/>
    </g>
    <path d="M76,66 C76,29 88,27 94,27 C102,27 112,31 112,66" fill="none" stroke="#8a3506" stroke-width="6.5" stroke-linecap="round"/>
    <path d="M76,66 C76,29 88,27 94,27 C102,27 112,31 112,66" fill="none" stroke="#c8611a" stroke-width="3.2" stroke-linecap="round"/>
    <circle cx="76" cy="69" r="2.4" fill="#8a3506"/><circle cx="112" cy="69" r="2.4" fill="#8a3506"/>
    <path d="M76,70 Q66,76 57,83" fill="none" stroke="#6b2804" stroke-width="1.3"/>
    <g transform="translate(52 94) rotate(-62)">
      <path d="M-12,-9 L10,-9 Q14,-9 14,-5 L14,5 Q14,9 10,9 L-12,9 L-20,0 Z" fill="url(#${u}-tg)" stroke="#d99a0c" stroke-width="1.4" stroke-linejoin="round"/>
      <circle cx="-13" cy="0" r="2.2" fill="#fff" stroke="#d99a0c" stroke-width="1"/>
      <text x="2" y="0" dy=".36em" text-anchor="middle" font-family="Fredoka, sans-serif" font-weight="600" font-size="9.5" fill="#a5660a">R$</text>
    </g>
  </g>
  <g filter="url(#${u}-ds)" transform="translate(157 110) rotate(9)">
    <path d="${shield}" fill="#163c94" transform="translate(0 3.5)"/>
    <path d="${shield}" fill="url(#${u}-sh)" stroke="url(#${u}-gd)" stroke-width="3.4" stroke-linejoin="round"/>
    <path d="M0,-22 Q11,-17.5 21,-19 L21,0 Q21,15 0,23.5 Q-21,15 -21,0 L-21,-19 Q-11,-17.5 0,-22 Z" fill="none" stroke="#8fc0ff" stroke-width="1.3" opacity=".7"/>
    <path d="M-11,0 L-3,8.5 L12,-8" fill="none" stroke="#0f2f78" stroke-width="8.5" stroke-linecap="round" stroke-linejoin="round" transform="translate(0 1.6)" opacity=".55"/>
    <path d="M-11,0 L-3,8.5 L12,-8" fill="none" stroke="#fff" stroke-width="7" stroke-linecap="round" stroke-linejoin="round"/>
    <path d="M-19,-15 Q-10,-14 -3,-17" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" opacity=".75"/>
  </g>`;
}

function coinStack(u, x, baseY, n, label) {
  let g = "";
  for (let i = 0; i < n; i++) {
    const y = baseY - i * 6.2;
    g += `<ellipse cx="${x}" cy="${y + 5}" rx="19" ry="6.6" fill="#b87908" stroke="#94600a" stroke-width="1.2"/>
      <rect x="${x - 19}" y="${y}" width="38" height="5" fill="url(#${u}-cs)"/>
      <line x1="${x - 19}" y1="${y}" x2="${x - 19}" y2="${y + 5}" stroke="#94600a" stroke-width="1.2"/><line x1="${x + 19}" y1="${y}" x2="${x + 19}" y2="${y + 5}" stroke="#94600a" stroke-width="1.2"/>
      <ellipse cx="${x}" cy="${y}" rx="19" ry="6.6" fill="url(#${u}-ct)" stroke="#c48a10" stroke-width="1.2"/>`;
  }
  const top = baseY - (n - 1) * 6.2;
  g += `<ellipse cx="${x}" cy="${top}" rx="13.5" ry="4.4" fill="none" stroke="#e0a21c" stroke-width="1.2"/>
    <g transform="translate(${x} ${top}) scale(1 .38)"><text x="0" y="0" dy=".36em" text-anchor="middle" font-family="Fredoka, sans-serif" font-weight="600" font-size="17" fill="#c48a10">${label}</text></g>
    <path d="M${x - 14},${top - 2.4} Q${x - 6},${top - 5.6} ${x + 3},${top - 6}" fill="none" stroke="#fff" stroke-width="1.6" stroke-linecap="round" opacity=".85"/>`;
  return g;
}
function illusTributario(u) {
  // Calculadora com a alíquota máxima do IR (27,5%) + pilhas de moedas
  const keys = [];
  for (let r = 0; r < 4; r++) for (let c = 0; c < 4; c++) {
    const op = c === 3, eq = r === 3 && c === 3;
    keys.push(`<rect x="${-24 + c * 13}" y="${-4 + r * 11.5}" width="10.5" height="9" rx="2.4" fill="${eq ? "#3ccf7a" : op ? "#ff8a2e" : "#eef1f8"}"/>
      <rect x="${-24 + c * 13}" y="${-4 + r * 11.5 + 7}" width="10.5" height="2" rx="1" fill="${eq ? "#1f9a55" : op ? "#cf5d0c" : "#b5bdd2"}"/>`);
  }
  return `<defs>
    <linearGradient id="${u}-cb" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#5b6390"/><stop offset=".5" stop-color="#3a4170"/><stop offset="1" stop-color="#262b52"/></linearGradient>
    <linearGradient id="${u}-sc" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#d9f7c4"/><stop offset="1" stop-color="#a7e08f"/></linearGradient>
    <linearGradient id="${u}-ct" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#fff2a8"/><stop offset=".5" stop-color="#ffd23a"/><stop offset="1" stop-color="#f0ac10"/></linearGradient>
    <linearGradient id="${u}-cs" x1="0" y1="0" x2="1" y2="0"><stop offset="0" stop-color="#f5b81c"/><stop offset=".35" stop-color="#ffd84a"/><stop offset="1" stop-color="#c98a0a"/></linearGradient>
  </defs>
  <g filter="url(#${u}-ds)" transform="translate(146 74) rotate(13)">
    <rect x="-31" y="-46" width="64" height="96" rx="9" fill="#1b1f40" transform="translate(2.5 3)"/>
    <rect x="-31" y="-46" width="64" height="96" rx="9" fill="url(#${u}-cb)" stroke="#1b1f40" stroke-width="1.8"/>
    <path d="M-25,-42.5 L24,-42.5" stroke="#8c94c4" stroke-width="2" stroke-linecap="round" opacity=".8"/>
    <rect x="-25" y="-36" width="52" height="25" rx="4" fill="#1b1f40"/>
    <rect x="-23" y="-34" width="48" height="21" rx="3" fill="url(#${u}-sc)"/>
    <text x="22" y="-23.5" dy=".36em" text-anchor="end" font-family="Fredoka, sans-serif" font-weight="600" font-size="14" fill="#2f5a1e">27,5%</text>
    ${keys.join("")}
  </g>
  <g filter="url(#${u}-ds)">
    ${coinStack(u, 116, 132, 4, "R$")}
    ${coinStack(u, 80, 128, 7, "R$")}
  </g>`;
}

function illusProcesso(u) {
  // Autos do processo (pastas com etiqueta e clipe) + martelo do juiz com base
  const folder = (fill, edge) => `
      <path d="M-32,-36 L-12,-36 L-8,-41 L8,-41 L12,-36 L32,-36 Q35,-36 35,-33 L35,40 Q35,43 32,43 L-32,43 Q-35,43 -35,40 L-35,-33 Q-35,-36 -32,-36 Z" fill="${fill}" stroke="${edge}" stroke-width="1.6" stroke-linejoin="round"/>`;
  return `<defs>
    <linearGradient id="${u}-mf" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#ffe1a0"/><stop offset=".55" stop-color="#f5c062"/><stop offset="1" stop-color="#dc9a36"/></linearGradient>
    <linearGradient id="${u}-bf" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#6aa8ff"/><stop offset="1" stop-color="#2d5fd0"/></linearGradient>
    <linearGradient id="${u}-wd" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#c47a3e"/><stop offset=".4" stop-color="#9a5524"/><stop offset="1" stop-color="#6a3512"/></linearGradient>
    <linearGradient id="${u}-wh" x1="0" y1="0" x2="1" y2="0"><stop offset="0" stop-color="#c47a3e"/><stop offset=".45" stop-color="#9a5524"/><stop offset="1" stop-color="#6a3512"/></linearGradient>
    <linearGradient id="${u}-gd" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#fff0b0"/><stop offset="1" stop-color="#e2a51c"/></linearGradient>
    <linearGradient id="${u}-bt" x1="0" y1="0" x2="1" y2="0"><stop offset="0" stop-color="#b56a30"/><stop offset=".4" stop-color="#8a4a1d"/><stop offset="1" stop-color="#5c2d0e"/></linearGradient>
  </defs>
  <g filter="url(#${u}-ds)" transform="translate(88 98) rotate(-12)">
    ${folder("url(#" + u + "-bf)", "#1f4aa8")}
  </g>
  <g filter="url(#${u}-ds)" transform="translate(98 102) rotate(-3)">
    <rect x="-28" y="-44" width="56" height="70" rx="2" fill="#f4f6fb" stroke="#c3cbe0" stroke-width="1.2" transform="rotate(4)"/>
    <rect x="-29" y="-40" width="56" height="70" rx="2" fill="#ffffff" stroke="#c3cbe0" stroke-width="1.2"/>
    ${folder("url(#" + u + "-mf)", "#c48224")}
    <path d="M-32,-33.6 L31,-33.6" stroke="#fff4d6" stroke-width="2" stroke-linecap="round"/>
    <rect x="-22" y="-20" width="44" height="30" rx="3" fill="#fffdf6" stroke="#d6a957" stroke-width="1.2"/>
    <text x="0" y="-12" dy=".36em" text-anchor="middle" font-family="Fredoka, sans-serif" font-weight="600" font-size="9.5" letter-spacing=".8" fill="#8a5414">AUTOS</text>
    <rect x="-15" y="-1" width="30" height="3" rx="1.5" fill="#e3cfa3"/>
    <rect x="-15" y="4.5" width="20" height="3" rx="1.5" fill="#e3cfa3"/>
    <path d="M-35,22 L35,22" stroke="#d1913a" stroke-width="1.3" opacity=".7"/>
    <g transform="translate(0 -40)">
      <rect x="-10" y="-5" width="20" height="10" rx="2.2" fill="#2a2f52" stroke="#151935" stroke-width="1.2"/>
      <path d="M-7,-5 L-5,-15 L5,-15 L7,-5" fill="none" stroke="#c9cfe0" stroke-width="2.2" stroke-linejoin="round"/>
      <rect x="-7" y="-3" width="14" height="2" rx="1" fill="#5d6596"/>
    </g>
  </g>
  <g filter="url(#${u}-ds)">
    <ellipse cx="150" cy="134" rx="27" ry="8" fill="#4a230a"/>
    <rect x="123" y="124" width="54" height="10" fill="url(#${u}-bt)"/>
    <ellipse cx="150" cy="124" rx="27" ry="8" fill="url(#${u}-wd)" stroke="#5c2d0e" stroke-width="1.4"/>
    <ellipse cx="150" cy="124" rx="19" ry="5" fill="none" stroke="#c98a52" stroke-width="1.2" opacity=".8"/>
    <path d="M127,121.5 Q138,117 150,116.4" fill="none" stroke="#e6b07a" stroke-width="1.8" stroke-linecap="round" opacity=".9"/>
  </g>
  <g filter="url(#${u}-ds)" transform="translate(146 98) rotate(38)">
    <rect x="-4.6" y="-88" width="9.2" height="80" rx="4.6" fill="url(#${u}-wh)" stroke="#5c2d0e" stroke-width="1.3"/>
    <path d="M-2.2,-84 L-2.2,-16" stroke="#e1a46b" stroke-width="1.6" stroke-linecap="round" opacity=".8"/>
    <ellipse cx="0" cy="-88" rx="6.4" ry="4.4" fill="url(#${u}-wh)" stroke="#5c2d0e" stroke-width="1.3"/>
    <rect x="-6" y="-22" width="12" height="5" rx="1.6" fill="url(#${u}-gd)" stroke="#b9821a" stroke-width=".9"/>
    <rect x="-27" y="-12" width="54" height="24" rx="7" fill="url(#${u}-wd)" stroke="#5c2d0e" stroke-width="1.5"/>
    <rect x="-18" y="-12.6" width="5.5" height="25.2" rx="1.2" fill="url(#${u}-gd)" stroke="#b9821a" stroke-width=".9"/>
    <rect x="12.5" y="-12.6" width="5.5" height="25.2" rx="1.2" fill="url(#${u}-gd)" stroke="#b9821a" stroke-width=".9"/>
    <ellipse cx="-27" cy="0" rx="4" ry="11.6" fill="#b4733c" stroke="#5c2d0e" stroke-width="1.3"/>
    <ellipse cx="27" cy="0" rx="4" ry="11.6" fill="#7a4018" stroke="#5c2d0e" stroke-width="1.3"/>
    <path d="M-21,-7.6 L21,-7.6" stroke="#e6b07a" stroke-width="2" stroke-linecap="round" opacity=".85"/>
  </g>`;
}

function illusEstatuto(u) {
  // Advocacia: pasta executiva com balança dourada + Estatuto da Advocacia (Lei 8.906/94)
  return `<defs>
    <linearGradient id="${u}-lt" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#c2405f"/><stop offset=".5" stop-color="#93223f"/><stop offset="1" stop-color="#5e1026"/></linearGradient>
    <linearGradient id="${u}-lf" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#d4506f"/><stop offset="1" stop-color="#a62b4a"/></linearGradient>
    <linearGradient id="${u}-gd" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#fff0b0"/><stop offset="1" stop-color="#e2a51c"/></linearGradient>
    <linearGradient id="${u}-nv" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#4a5bc4"/><stop offset=".55" stop-color="#2a3592"/><stop offset="1" stop-color="#18205e"/></linearGradient>
    <linearGradient id="${u}-pg" x1="0" y1="0" x2="1" y2="0"><stop offset="0" stop-color="#fffaf0"/><stop offset="1" stop-color="#ead8b0"/></linearGradient>
  </defs>
  <g filter="url(#${u}-ds)" transform="translate(138 90) scale(.86) rotate(7) translate(-140 -96)">
    <path d="M118,58 L118,42 Q118,34 126,34 L154,34 Q162,34 162,42 L162,58" fill="none" stroke="#4a0c1d" stroke-width="10" stroke-linecap="round"/>
    <path d="M118,58 L118,42 Q118,34 126,34 L154,34 Q162,34 162,42 L162,58" fill="none" stroke="url(#${u}-lt)" stroke-width="6.4" stroke-linecap="round"/>
    <path d="M122,40 Q124,37.6 128,37.6 L150,37.6" fill="none" stroke="#f08aa3" stroke-width="1.6" stroke-linecap="round" opacity=".85"/>
    <rect x="84" y="56" width="112" height="82" rx="10" fill="#4a0c1d" transform="translate(0 3)"/>
    <rect x="84" y="56" width="112" height="82" rx="10" fill="url(#${u}-lt)" stroke="#4a0c1d" stroke-width="1.8"/>
    <path d="M84,66 Q84,56 94,56 L186,56 Q196,56 196,66 L196,84 Q140,94 84,84 Z" fill="url(#${u}-lf)" stroke="#4a0c1d" stroke-width="1.6" stroke-linejoin="round"/>
    <path d="M93,60 L187,60" stroke="#ff9bb3" stroke-width="2" stroke-linecap="round" opacity=".8"/>
    <path d="M90,90 Q140,100 190,90" fill="none" stroke="#5e1026" stroke-width="1.3" stroke-dasharray="3 3" opacity=".8"/>
    <g>
      <rect x="104" y="80" width="12" height="13" rx="2.4" fill="url(#${u}-gd)" stroke="#b9821a" stroke-width="1"/>
      <rect x="164" y="80" width="12" height="13" rx="2.4" fill="url(#${u}-gd)" stroke="#b9821a" stroke-width="1"/>
      <rect x="107.5" y="85" width="5" height="2.2" rx="1.1" fill="#b9821a"/><rect x="167.5" y="85" width="5" height="2.2" rx="1.1" fill="#b9821a"/>
    </g>
    <g transform="translate(140 114)" fill="none" stroke="url(#${u}-gd)" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
      <path d="M0,-14 L0,12 M-8,12 L8,12 M-17,-9 L17,-9"/>
      <circle cx="0" cy="-15.5" r="2" fill="#ffd34d" stroke="none"/>
      <path d="M-17,-9 L-22,2 M-17,-9 L-12,2 M17,-9 L12,2 M17,-9 L22,2"/>
      <path d="M-23,2 Q-17,8 -11,2 Z M11,2 Q17,8 23,2 Z" fill="#ffd34d"/>
    </g>
  </g>
  <g filter="url(#${u}-ds)" transform="translate(78 108) rotate(-10) scale(.74)">
    <rect x="-23" y="-37" width="52" height="72" rx="5" fill="#101640"/>
    <rect x="-22" y="-34" width="48" height="66" rx="2" fill="url(#${u}-pg)"/>
    <line x1="22" y1="-31" x2="22" y2="29" stroke="#dcc79a" stroke-width="1"/>
    <line x1="18.5" y1="-32" x2="18.5" y2="30" stroke="#dcc79a" stroke-width="1"/>
    <rect x="-30" y="-38" width="48" height="72" rx="5" fill="url(#${u}-nv)" stroke="#18205e" stroke-width="1.6"/>
    <rect x="-30" y="-38" width="8" height="72" rx="4" fill="#202a7a"/>
    <line x1="-21.5" y1="-36" x2="-21.5" y2="32" stroke="#7d8ff0" stroke-width="1.2" opacity=".7"/>
    <rect x="-17" y="-31" width="30" height="58" rx="3" fill="none" stroke="url(#${u}-gd)" stroke-width="1.6"/>
    <path d="${starPath(-2, -19, 5.5)}" fill="url(#${u}-gd)" stroke="#c98d12" stroke-width=".8" stroke-linejoin="round"/>
    <text x="-2" y="-3" dy=".36em" text-anchor="middle" font-family="Fredoka, sans-serif" font-weight="600" font-size="5.3" letter-spacing="0" fill="url(#${u}-gd)">ESTATUTO</text>
    <rect x="-12" y="4" width="20" height="1.6" rx=".8" fill="#e2a51c" opacity=".8"/>
    <text x="-2" y="13" dy=".36em" text-anchor="middle" font-family="Fredoka, sans-serif" font-weight="600" font-size="6.6" fill="#ffe08a">8.906/94</text>
    <path d="M-27,-35 L14,-35" stroke="#a8b6ff" stroke-width="2" stroke-linecap="round" opacity=".8"/>
  </g>`;
}

function illusPrevidencia(u) {
  // Cofrinho recebendo moeda (contribuições) + ampulheta (tempo de contribuição)
  return `<defs>
    <radialGradient id="${u}-pig" cx=".38" cy=".3" r=".85"><stop offset="0" stop-color="#ffd0df"/><stop offset=".5" stop-color="#ff8fb2"/><stop offset="1" stop-color="#d94a79"/></radialGradient>
    <linearGradient id="${u}-pd" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#f06c96"/><stop offset="1" stop-color="#c2395f"/></linearGradient>
    <linearGradient id="${u}-lg" x1="0" y1="0" x2="1" y2="0"><stop offset="0" stop-color="#ffa6c3"/><stop offset=".45" stop-color="#f27aa0"/><stop offset="1" stop-color="#cc406a"/></linearGradient>
    <linearGradient id="${u}-lgf" x1="0" y1="0" x2="1" y2="0"><stop offset="0" stop-color="#d14a74"/><stop offset="1" stop-color="#a5294f"/></linearGradient>
    <linearGradient id="${u}-ct" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#fff2a8"/><stop offset=".5" stop-color="#ffd23a"/><stop offset="1" stop-color="#e09a0c"/></linearGradient>
    <linearGradient id="${u}-wd" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#c47a3e"/><stop offset="1" stop-color="#7a4018"/></linearGradient>
    <linearGradient id="${u}-gl" x1="0" y1="0" x2="1" y2="0"><stop offset="0" stop-color="#ffffff" stop-opacity=".95"/><stop offset=".5" stop-color="#dff1ff" stop-opacity=".7"/><stop offset="1" stop-color="#a9d2f5" stop-opacity=".85"/></linearGradient>
    <linearGradient id="${u}-sd" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#ffd25a"/><stop offset="1" stop-color="#f29a1a"/></linearGradient>
  </defs>
  <g filter="url(#${u}-ds)">
    ${[[79, 126.5], [119, 128]].map(([x, b]) => `<path d="M${x},106 L${x + .8},${b - 4} Q${x + .8},${b} ${x + 7.5},${b} Q${x + 14.2},${b} ${x + 14.2},${b - 4} L${x + 15},106 Z" fill="url(#${u}-lgf)" stroke="#a82c52" stroke-width="1.5" stroke-linejoin="round"/>`).join("")}
    ${[[85, 131], [125, 132.5]].map(([x, b]) => `<path d="M${x},106 L${x + 1},${b - 4.5} Q${x + 1},${b} ${x + 8.5},${b} Q${x + 16},${b} ${x + 16},${b - 4.5} L${x + 17},106 Z" fill="url(#${u}-lg)" stroke="#a82c52" stroke-width="1.6" stroke-linejoin="round"/>
      <path d="M${x + 4.2},${b - 9} L${x + 4.2},${b - 4.6}" stroke="#ffc2d6" stroke-width="2.2" stroke-linecap="round" opacity=".85"/>`).join("")}
    <path d="M155,86 q11,-3 10,-11 q-1,-6 -7,-3 q-4,3 1,7" fill="none" stroke="#c2395f" stroke-width="3.2" stroke-linecap="round"/>
    <path d="M80,72 Q74,52 82,46 Q92,50 96,64 Z" fill="#d94a79" stroke="#a82c52" stroke-width="1.8" stroke-linejoin="round" transform="translate(88 68) scale(.76) translate(-88 -68)"/>
    <ellipse cx="114" cy="92" rx="45" ry="33" fill="url(#${u}-pig)" stroke="#b8345e" stroke-width="1.8"/>
    <g transform="translate(106 65) scale(.76) translate(-106 -65)">
      <path d="M98,66 Q100,44 112,42 Q118,52 114,66 Z" fill="url(#${u}-pd)" stroke="#a82c52" stroke-width="2" stroke-linejoin="round"/>
      <path d="M103,63 Q104.5,52 110,49.5 Q112.5,56 110.5,63 Q107,64.5 103,63 Z" fill="#ffc2d6"/>
    </g>
    <g transform="translate(131 66) rotate(14)">
      <ellipse cx="0" cy="1.5" rx="10.6" ry="3" fill="#ffc2d6"/>
      <ellipse cx="0" cy="0" rx="10" ry="2.5" fill="#6e1634"/>
    </g>
    <ellipse cx="72" cy="101" rx="9.5" ry="11.5" fill="#d94a79" stroke="#b8345e" stroke-width="1.6"/>
    <ellipse cx="68.5" cy="101" rx="9.5" ry="11.5" fill="#ffa6c3" stroke="#b8345e" stroke-width="1.6"/>
    <ellipse cx="65.4" cy="101" rx="1.9" ry="3.6" fill="#a82c52"/>
    <ellipse cx="71.6" cy="101" rx="1.9" ry="3.6" fill="#a82c52"/>
    <path d="M62.5,93.5 Q65,91 68.5,91" fill="none" stroke="#fff" stroke-width="1.6" stroke-linecap="round" opacity=".9"/>
    <ellipse cx="74" cy="84" rx="2.9" ry="3.8" fill="#3a1024"/>
    <circle cx="73.1" cy="82.6" r="1.1" fill="#fff"/>
    <ellipse cx="91" cy="83" rx="3.4" ry="4.4" fill="#3a1024"/>
    <circle cx="89.9" cy="81.4" r="1.3" fill="#fff"/>
    <ellipse cx="94" cy="98" rx="7" ry="4" fill="#ff6f9c" opacity=".45"/>
  </g>
  <g filter="url(#${u}-ds)" transform="translate(8.4 -6)">
    <circle cx="124" cy="44" r="14" fill="#b87908"/>
    <circle cx="122.6" cy="42.6" r="14" fill="url(#${u}-ct)" stroke="#c48a10" stroke-width="1.3"/>
    <circle cx="122.6" cy="42.6" r="10" fill="none" stroke="#e0a21c" stroke-width="1.2"/>
    <text x="122.6" y="42.6" dy=".36em" text-anchor="middle" font-family="Fredoka, sans-serif" font-weight="600" font-size="10" fill="#b8790a">R$</text>
    <path d="M113,36 Q116,31 121,30" fill="none" stroke="#fff" stroke-width="1.8" stroke-linecap="round"/>
    <path d="M142,34 q4,6 2,12 M147,30 q6,8 3,18" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" opacity=".7"/>
  </g>
  <g filter="url(#${u}-ds)" transform="translate(166 108) rotate(10)">
    <rect x="-17" y="-29" width="34" height="7" rx="3" fill="url(#${u}-wd)" stroke="#5c2d0e" stroke-width="1.3"/>
    <rect x="-17" y="22" width="34" height="7" rx="3" fill="url(#${u}-wd)" stroke="#5c2d0e" stroke-width="1.3"/>
    <path d="M-12,-22 Q-12,-6 -2,0 Q-12,6 -12,22 L12,22 Q12,6 2,0 Q12,-6 12,-22 Z" fill="url(#${u}-gl)" stroke="#8fb6dc" stroke-width="1.3"/>
    <path d="M-8,-12 Q-6,-5 -1,-2 L1,-2 Q6,-5 8,-12 Z" fill="url(#${u}-sd)"/>
    <path d="M-.7,0 L-.7,14 L.7,14 L.7,0 Z" fill="#f2a321"/>
    <path d="M-11,22 Q-9,12 0,11 Q9,12 11,22 Z" fill="url(#${u}-sd)"/>
    <path d="M-9,-19 Q-9,-10 -4,-5" fill="none" stroke="#fff" stroke-width="1.8" stroke-linecap="round"/>
    <rect x="-15.5" y="-22" width="3.6" height="44" rx="1.8" fill="url(#${u}-wd)" stroke="#5c2d0e" stroke-width="1"/>
    <rect x="11.9" y="-22" width="3.6" height="44" rx="1.8" fill="url(#${u}-wd)" stroke="#5c2d0e" stroke-width="1"/>
    <path d="M-14,-27 L14,-27" stroke="#e6b07a" stroke-width="1.6" stroke-linecap="round"/>
  </g>`;
}

function illusTrabalho(u) {
  // Capacete de segurança (atrás) + Carteira de Trabalho (à frente)
  const dome = "M-34,0 A34,33 0 0 1 34,0 A34,7.5 0 0 1 -34,0 Z";
  return `<defs>
    <radialGradient id="${u}-hy" cx=".35" cy=".25" r=".95"><stop offset="0" stop-color="#fff3a0"/><stop offset=".45" stop-color="#ffd23f"/><stop offset="1" stop-color="#eb9a00"/></radialGradient>
    <linearGradient id="${u}-hb" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#ffd84d"/><stop offset="1" stop-color="#f2a800"/></linearGradient>
    <linearGradient id="${u}-hr" x1="0" y1="0" x2="1" y2="0"><stop offset="0" stop-color="#fff6b8"/><stop offset=".5" stop-color="#ffe066"/><stop offset="1" stop-color="#f4b400"/></linearGradient>
    <linearGradient id="${u}-wr" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#f4f6fb"/><stop offset=".5" stop-color="#c3c9da"/><stop offset="1" stop-color="#8e97b2"/></linearGradient>
    <linearGradient id="${u}-cv" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#3f6fe0"/><stop offset=".55" stop-color="#22479e"/><stop offset="1" stop-color="#152f72"/></linearGradient>
    <linearGradient id="${u}-pg" x1="0" y1="0" x2="1" y2="0"><stop offset="0" stop-color="#fffaf0"/><stop offset="1" stop-color="#e6d3aa"/></linearGradient>
    <linearGradient id="${u}-gd" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#fff0b0"/><stop offset="1" stop-color="#e2a51c"/></linearGradient>
  </defs>
  <g filter="url(#${u}-ds)" transform="translate(127 66) rotate(8) scale(1.22)">
    <ellipse cx="-3" cy="5" rx="48" ry="12.5" fill="#c97f00"/>
    <ellipse cx="-3" cy="2" rx="48" ry="12.5" fill="url(#${u}-hb)" stroke="#c77d00" stroke-width="1.6"/>
    <path d="M-48,1 A48,12.5 0 0 0 -30,11.6" fill="none" stroke="#fff3b0" stroke-width="1.8" stroke-linecap="round" opacity=".9"/>
    <path d="${dome}" fill="url(#${u}-hy)" stroke="#c77d00" stroke-width="1.7" stroke-linejoin="round"/>
    <path d="M-20,-26.6 Q-25,-12 -23,5.6" fill="none" stroke="#e39400" stroke-width="1.6" stroke-linecap="round" opacity=".75"/>
    <path d="M20,-26.6 Q25,-12 23,5.6" fill="none" stroke="#d98a00" stroke-width="1.6" stroke-linecap="round" opacity=".75"/>
    <path d="M-7.5,-32 Q0,-35 7.5,-32 L8.6,6.6 Q0,8.4 -8.6,6.6 Z" fill="url(#${u}-hr)" stroke="#c77d00" stroke-width="1.4" stroke-linejoin="round"/>
    <path d="M-4.4,-30.6 L-4.8,3.6" stroke="#fff" stroke-width="1.6" stroke-linecap="round" opacity=".85"/>
    <path d="M-28,-10 Q-24,-24 -12,-30" fill="none" stroke="#fff" stroke-width="3" stroke-linecap="round" opacity=".85"/>
  </g>
  <g filter="url(#${u}-ds)" transform="translate(138 103) rotate(36)">
    <rect x="-44" y="-5.6" width="68" height="11.2" rx="5.6" fill="url(#${u}-wr)" stroke="#5f6884" stroke-width="1.5"/>
    <path d="M-40,-2.4 L18,-2.4" stroke="#fff" stroke-width="1.8" stroke-linecap="round" opacity=".85"/>
    <path d="M20,-6.5 L24,-12 Q33,-16.5 42,-12 L46.5,-6 L37,-3.8 L37,3.8 L46.5,6 L42,12 Q33,16.5 24,12 L20,6.5 Z" fill="url(#${u}-wr)" stroke="#5f6884" stroke-width="1.6" stroke-linejoin="round"/>
    <path d="M25,-9.6 Q32,-13 39,-10" fill="none" stroke="#fff" stroke-width="1.8" stroke-linecap="round" opacity=".9"/>
    <circle cx="-36" cy="0" r="2.6" fill="#7b84a0"/>
  </g>
  <g filter="url(#${u}-ds)" transform="translate(101 106) rotate(-8) scale(1.04)">
    <rect x="-24" y="-34" width="51" height="70" rx="5" fill="#0f2257"/>
    <rect x="-23" y="-33" width="48" height="66" rx="3" fill="url(#${u}-pg)"/>
    <line x1="21.6" y1="-30" x2="21.6" y2="30" stroke="#d9c597" stroke-width="1"/>
    <line x1="18.2" y1="-31" x2="18.2" y2="31" stroke="#d9c597" stroke-width="1"/>
    <rect x="-28" y="-36" width="47" height="70" rx="5.5" fill="url(#${u}-cv)" stroke="#132a66" stroke-width="1.6"/>
    <path d="M-24.5,-33 L15,-33" stroke="#7ea2ff" stroke-width="2" stroke-linecap="round" opacity=".85"/>
    <path d="M-25,-30 L-25,29" stroke="#3b63c8" stroke-width="1.4" stroke-linecap="round" opacity=".8"/>
    <text x="-4.5" y="-24" dy=".36em" text-anchor="middle" font-family="Fredoka, sans-serif" font-weight="600" font-size="5.6" letter-spacing=".2" fill="url(#${u}-gd)">CARTEIRA DE</text>
    <text x="-4.5" y="-16" dy=".36em" text-anchor="middle" font-family="Fredoka, sans-serif" font-weight="600" font-size="7.4" letter-spacing=".2" fill="url(#${u}-gd)">TRABALHO</text>
    <circle cx="-4.5" cy="4" r="10.5" fill="none" stroke="url(#${u}-gd)" stroke-width="1.6"/>
    <circle cx="-4.5" cy="4" r="7.6" fill="#1a3a8a" stroke="#e2a51c" stroke-width=".8" stroke-dasharray="1.2 1.4"/>
    <path d="${starPath(-4.5, 4.6, 5.4)}" fill="url(#${u}-gd)" stroke="#c98d12" stroke-width=".7" stroke-linejoin="round"/>
    <rect x="-17" y="22" width="25" height="1.8" rx=".9" fill="#e2a51c" opacity=".85"/>
    <rect x="-13" y="26.4" width="17" height="1.6" rx=".8" fill="#e2a51c" opacity=".6"/>
  </g>`;
}

function illusAdministrativo(u) {
  // Ato administrativo: portaria com carimbo "DEFERIDO" + carimbo de madeira recém-levantado
  return `<defs>
    <linearGradient id="${u}-pp" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#ffffff"/><stop offset="1" stop-color="#e1e7f4"/></linearGradient>
    <linearGradient id="${u}-wf" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#c98245"/><stop offset="1" stop-color="#8a4a1d"/></linearGradient>
    <linearGradient id="${u}-wt" x1="0" y1="0" x2="1" y2="0"><stop offset="0" stop-color="#f0b77c"/><stop offset="1" stop-color="#cf8a4c"/></linearGradient>
    <linearGradient id="${u}-ws" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#8a4a1d"/><stop offset="1" stop-color="#5e2f10"/></linearGradient>
    <linearGradient id="${u}-nk" x1="0" y1="0" x2="1" y2="0"><stop offset="0" stop-color="#e4a468"/><stop offset=".45" stop-color="#b56a30"/><stop offset="1" stop-color="#7a4018"/></linearGradient>
    <radialGradient id="${u}-kb" cx=".35" cy=".3" r=".8"><stop offset="0" stop-color="#ff8a95"/><stop offset=".5" stop-color="#e2384b"/><stop offset="1" stop-color="#a51b2c"/></radialGradient>
    <linearGradient id="${u}-gd" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#fff0b0"/><stop offset="1" stop-color="#e2a51c"/></linearGradient>
  </defs>
  <g filter="url(#${u}-ds)" transform="translate(-8 2) rotate(-6 98 100)">
    <rect x="62" y="56" width="72" height="88" rx="4" fill="url(#${u}-pp)" stroke="#c3cde6" stroke-width="1.3"/>
    <circle cx="98" cy="67" r="5.4" fill="none" stroke="url(#${u}-gd)" stroke-width="1.4"/>
    <path d="${starPath(98, 67.4, 3.4)}" fill="url(#${u}-gd)"/>
    <text x="98" y="79" dy=".36em" text-anchor="middle" font-family="Fredoka, sans-serif" font-weight="600" font-size="7" letter-spacing=".6" fill="#2b3a7a">PORTARIA</text>
    <rect x="72" y="88" width="52" height="3.4" rx="1.7" fill="#c6d0e8"/>
    <rect x="72" y="95" width="46" height="3.4" rx="1.7" fill="#c6d0e8"/>
    <rect x="72" y="102" width="52" height="3.4" rx="1.7" fill="#c6d0e8"/>
    <rect x="72" y="109" width="30" height="3.4" rx="1.7" fill="#c6d0e8"/>
    <g transform="translate(103 126) rotate(-11)" opacity=".92">
      <rect x="-25" y="-9" width="50" height="18" rx="2.5" fill="#e2384b" fill-opacity=".08" stroke="#d92f43" stroke-width="2"/>
      <rect x="-21.6" y="-5.8" width="43.2" height="11.6" rx="1.5" fill="none" stroke="#d92f43" stroke-width=".8"/>
      <text x="0" y="0" dy=".36em" text-anchor="middle" font-family="Fredoka, sans-serif" font-weight="600" font-size="7.2" letter-spacing=".5" fill="#d92f43">DEFERIDO</text>
    </g>
  </g>
  <g filter="url(#${u}-ds)" transform="translate(151 72) rotate(14) scale(1.34)">
    <path d="M24,19 L32,13 L32,19 L24,25.5 Z" fill="#9c1a2b" stroke="#6e0f1c" stroke-width="1.2" stroke-linejoin="round"/>
    <path d="M-24,19 L24,19 L24,25.5 L-24,25.5 Z" fill="#d42f43" stroke="#6e0f1c" stroke-width="1.2" stroke-linejoin="round"/>
    <path d="M-22,21.4 L22,21.4" stroke="#ff7d8c" stroke-width="1.1" stroke-linecap="round" opacity=".8"/>
    <path d="M24,6 L32,0 L32,14 L24,20 Z" fill="url(#${u}-ws)" stroke="#4e260c" stroke-width="1.2" stroke-linejoin="round"/>
    <rect x="-24" y="6" width="48" height="14" rx="1.5" fill="url(#${u}-wf)" stroke="#4e260c" stroke-width="1.3"/>
    <path d="M-24,6 L-16,0 L32,0 L24,6 Z" fill="url(#${u}-wt)" stroke="#4e260c" stroke-width="1.2" stroke-linejoin="round"/>
    <path d="M-21,9.4 L21,9.4" stroke="#e6b07a" stroke-width="1.6" stroke-linecap="round" opacity=".8"/>
    <rect x="-1.5" y="-26" width="11" height="29" rx="3" fill="url(#${u}-nk)" stroke="#4e260c" stroke-width="1.2"/>
    <ellipse cx="4" cy="3" rx="7.5" ry="2.4" fill="#a4602b" opacity=".6"/>
    <ellipse cx="4" cy="-35" rx="14" ry="12.5" fill="url(#${u}-kb)" stroke="#8f1424" stroke-width="1.4"/>
    <path d="M-5,-41 Q-1,-45.5 5,-45.6" fill="none" stroke="#fff" stroke-width="2.4" stroke-linecap="round" opacity=".9"/>
    <path d="M0.6,-22 L0.6,0" stroke="#f7c79a" stroke-width="1.5" stroke-linecap="round" opacity=".85"/>
  </g>`;
}

function box3d(x0, x1, y0, y1, dx, dy, front, side, top, edge) {
  return `<path d="M${x1},${y0} L${x1 + dx},${y0 + dy} L${x1 + dx},${y1 + dy} L${x1},${y1} Z" fill="${side}" stroke="${edge}" stroke-width="1.4" stroke-linejoin="round"/>
    <path d="M${x0},${y0} L${x0 + dx},${y0 + dy} L${x1 + dx},${y0 + dy} L${x1},${y0} Z" fill="${top}" stroke="${edge}" stroke-width="1.4" stroke-linejoin="round"/>
    <rect x="${x0}" y="${y0}" width="${x1 - x0}" height="${y1 - y0}" fill="${front}" stroke="${edge}" stroke-width="1.5" stroke-linejoin="round"/>`;
}
function illusEmpresarial(u) {
  // Sede da empresa (prédios de vidro) + gráfico de barras em 3D + seta de crescimento
  const win = (x0, cols, y0, rows, w, h, gx, gy, lit) => {
    let s = "";
    for (let r = 0; r < rows; r++) for (let c = 0; c < cols; c++) {
      const on = lit.includes(r * cols + c);
      s += `<rect x="${x0 + c * (w + gx)}" y="${y0 + r * (h + gy)}" width="${w}" height="${h}" rx="1" fill="${on ? "#ffe27a" : "#bfe2ff"}" opacity="${on ? 1 : .8}"/>`;
    }
    return s;
  };
  return `<defs>
    <linearGradient id="${u}-gf" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#6fb2ff"/><stop offset="1" stop-color="#2a62d0"/></linearGradient>
    <linearGradient id="${u}-gs" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#2b58c0"/><stop offset="1" stop-color="#1a3a8a"/></linearGradient>
    <linearGradient id="${u}-bf" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#9fb6ff"/><stop offset="1" stop-color="#5c74d6"/></linearGradient>
    <linearGradient id="${u}-yf" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#ffe066"/><stop offset="1" stop-color="#f5a800"/></linearGradient>
    <linearGradient id="${u}-ar" x1="0" y1="1" x2="1" y2="0"><stop offset="0" stop-color="#22b864"/><stop offset="1" stop-color="#5df09a"/></linearGradient>
  </defs>
  <g transform="translate(9 0)">
  <g filter="url(#${u}-ds)">
    ${box3d(66, 98, 60, 136, 12, -6, "url(#" + u + "-bf)", "#3f56b0", "#c9d6ff", "#2b3c8e")}
    ${win(71, 3, 67, 7, 6, 5.4, 4, 4.4, [4, 9, 13])}
    ${box3d(102, 144, 28, 136, 14, -7, "url(#" + u + "-gf)", "url(#" + u + "-gs)", "#b9dcff", "#1a3f9a")}
    <path d="M144,28 L158,21 L158,129" fill="none" stroke="#1a3f9a" stroke-width="1.4"/>
    ${win(108, 4, 36, 10, 6, 5.6, 3.6, 4.4, [2, 7, 9, 18, 21, 27, 32])}
    <g stroke="#6f93e6" stroke-width="1.2" opacity=".8">${Array.from({ length: 10 }, (_, i) => `<line x1="147" y1="${33 + i * 10 - 1.5}" x2="155" y2="${29 + i * 10 - 1.5}"/>`).join("")}</g>
    <path d="M105,31 L105,132" stroke="#e6f3ff" stroke-width="2" stroke-linecap="round" opacity=".85"/>
    <rect x="118" y="22" width="2.4" height="10" fill="#9fb0d6"/><circle cx="119.2" cy="21" r="2.2" fill="#ff5a6a"/>
  </g>
  <g filter="url(#${u}-ds)">
    ${box3d(56, 70, 116, 138, 7, -4, "url(#" + u + "-yf)", "#d48a00", "#fff0a6", "#b06f00")}
    ${box3d(76, 90, 102, 138, 7, -4, "url(#" + u + "-yf)", "#d48a00", "#fff0a6", "#b06f00")}
    ${box3d(96, 110, 86, 138, 7, -4, "url(#" + u + "-yf)", "#d48a00", "#fff0a6", "#b06f00")}
    <path d="M58.4,119 L58.4,135 M78.4,105 L78.4,135 M98.4,89 L98.4,135" stroke="#fff6c8" stroke-width="1.8" stroke-linecap="round" opacity=".9"/>
  </g>
  <g filter="url(#${u}-ds)">
    <path d="M50,108 L84,88 L100,98 L150,60" fill="none" stroke="#0f6a38" stroke-width="10.5" stroke-linecap="round" stroke-linejoin="round"/>
    <path d="M50,108 L84,88 L100,98 L150,60" fill="none" stroke="url(#${u}-ar)" stroke-width="7" stroke-linecap="round" stroke-linejoin="round"/>
    <path d="M140.5,52.5 L163,49 L157.5,71 Z" fill="url(#${u}-ar)" stroke="#0f6a38" stroke-width="2" stroke-linejoin="round"/>
    <path d="M53,104.6 L83.6,86.6" stroke="#b5ffd4" stroke-width="1.8" stroke-linecap="round" opacity=".9"/>
  </g>
  </g>`;
}

function illusCivil(u) {
  // Contrato assinado com lacre de cera + caneta-tinteiro saindo do brasão
  const lumps = Array.from({ length: 10 }, (_, i) => {
    const a = i * 36 * Math.PI / 180; return `<circle cx="${(142 + 12.6 * Math.cos(a)).toFixed(1)}" cy="${(120 + 12.6 * Math.sin(a)).toFixed(1)}" r="4.6"/>`;
  }).join("");
  const paper = "M70,38 H138 L158,58 V134 Q158,140 152,140 H70 Q64,140 64,134 V44 Q64,38 70,38 Z";
  return `<defs>
    <linearGradient id="${u}-pp" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#ffffff"/><stop offset="1" stop-color="#e3e9f6"/></linearGradient>
    <linearGradient id="${u}-sl" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#ff5d6b"/><stop offset="1" stop-color="#a5162b"/></linearGradient>
    <linearGradient id="${u}-gd" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#ffe7a0"/><stop offset="1" stop-color="#dba01c"/></linearGradient>
    <linearGradient id="${u}-bar" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#5f7bf0"/><stop offset=".45" stop-color="#2f43b8"/><stop offset="1" stop-color="#1b2477"/></linearGradient>
    <linearGradient id="${u}-grip" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#4a4f6c"/><stop offset="1" stop-color="#15182a"/></linearGradient>
  </defs>
  <g filter="url(#${u}-ds)">
    <g transform="rotate(5 112 90)"><path d="${paper}" fill="#cdd6ee"/></g>
    <g transform="rotate(-9 112 90)">
      <path d="${paper}" fill="url(#${u}-pp)" stroke="#c4cee8" stroke-width="1.2"/>
      <path d="M138,38 L138,52 Q138,58 144,58 L158,58 Z" fill="#c3cde6"/>
      <path d="M138.6,39 L157,57.4" stroke="#fff" stroke-width="1.2" opacity=".8"/>
      <rect x="74" y="50" width="44" height="6.5" rx="3.2" fill="#3d4fa6"/>
      <rect x="74" y="65" width="74" height="4" rx="2" fill="#c6d0e8"/>
      <rect x="74" y="74" width="66" height="4" rx="2" fill="#c6d0e8"/>
      <rect x="74" y="83" width="74" height="4" rx="2" fill="#c6d0e8"/>
      <rect x="74" y="92" width="48" height="4" rx="2" fill="#c6d0e8"/>
      <rect x="74" y="120" width="48" height="1.6" rx=".8" fill="#a9b5d4"/>
      <path d="M76,116 c5,-11 10,-9 8,-1 s6,-12 10,-6 s3,7 9,-2 s7,-4 15,1" fill="none" stroke="#2f5bd3" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round"/>
      <g fill="url(#${u}-sl)">${lumps}<circle cx="142" cy="120" r="13.5"/></g>
      <circle cx="142" cy="120" r="8.6" fill="none" stroke="#8a1021" stroke-width="1.6" opacity=".65"/>
      <text x="142" y="120" dy=".36em" text-anchor="middle" font-family="Fredoka, sans-serif" font-weight="600" font-size="11" fill="#8a1021" opacity=".75">§</text>
      <path d="M131.5,113 A12,12 0 0 1 140,107.6" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" opacity=".7"/>
    </g>
    <g transform="translate(117 109) rotate(-48)">
      <path d="M0,0 L18,-6.8 Q22.5,0 18,6.8 Z" fill="url(#${u}-gd)" stroke="#b9821a" stroke-width=".8" stroke-linejoin="round"/>
      <line x1="2.5" y1="0" x2="14" y2="0" stroke="#9a6a10" stroke-width="1"/>
      <circle cx="14.5" cy="0" r="1.5" fill="#9a6a10"/>
      <rect x="17" y="-8" width="16" height="16" rx="3" fill="url(#${u}-grip)"/>
      <rect x="35" y="-10.5" width="85" height="21" rx="10.5" fill="url(#${u}-bar)"/>
      <rect x="31" y="-10.5" width="6" height="21" rx="2" fill="url(#${u}-gd)"/>
      <rect x="80" y="-11" width="6" height="22" rx="2" fill="url(#${u}-gd)"/>
      <rect x="42" y="-7.4" width="72" height="3.2" rx="1.6" fill="#fff" opacity=".55"/>
      <rect x="86" y="-14" width="27" height="4.2" rx="2.1" fill="url(#${u}-gd)"/>
      <circle cx="111" cy="-11.6" r="2.8" fill="url(#${u}-gd)"/>
      <ellipse cx="118.5" cy="0" rx="2.2" ry="7" fill="url(#${u}-gd)"/>
    </g>
  </g>`;
}

const ICONS = {
  penal: { label: "Direito Penal", draw: illusPenal },
  civil: { label: "Direito Civil", draw: illusCivil },
  const: { label: "Direito Constitucional", draw: illusConst },
  consumidor: { label: "Direito do Consumidor", draw: illusConsumidor },
  tributario: { label: "Direito Tributário", draw: illusTributario },
  processo: { label: "Processo", draw: illusProcesso },
  estatuto: { label: "Estatuto e Ética", draw: illusEstatuto },
  previdencia: { label: "Direito Previdenciário", draw: illusPrevidencia },
  trabalho: { label: "Direito do Trabalho", draw: illusTrabalho },
  administrativo: { label: "Direito Administrativo", draw: illusAdministrativo },
  empresarial: { label: "Direito Empresarial", draw: illusEmpresarial }
};

/* ---------- Texto da faixa ---------- */
let measureCtx = null;
function fitSize(text) {
  if (!text) return 20;
  if (!measureCtx) measureCtx = document.createElement("canvas").getContext("2d");
  measureCtx.font = '600 20px "Fredoka", sans-serif';
  const w = measureCtx.measureText(text).width + text.length * 0.6;
  return clamp(20 * 138 / w, 10, 21).toFixed(2);
}

/* ---------- Badge ---------- */
function starPath(cx, cy, R) {
  const r = R * 0.5, pts = [];
  for (let i = 0; i < 10; i++) {
    const a = (-90 + i * 36) * Math.PI / 180, rr = i % 2 ? r : R;
    pts.push((cx + rr * Math.cos(a)).toFixed(2) + "," + (cy + rr * Math.sin(a)).toFixed(2));
  }
  return "M" + pts.join(" L") + " Z";
}
function starsSVG(u, n) {
  if (!n) return "";
  const gap = 23, out = [];
  for (let i = 0; i < n; i++) {
    const x = 120 + (i - (n - 1) / 2) * gap;
    const dx = (x - 120) / 42;
    const y = 194 - dx * dx * 7;
    const big = n % 2 === 1 && i === (n - 1) / 2;
    const R = big ? 10.6 : 9.2;
    out.push(`<g>
      <path d="${starPath(x, y, R)}" fill="#b5560a" stroke="#b5560a" stroke-width="4.4" stroke-linejoin="round"/>
      <path d="${starPath(x, y, R)}" fill="url(#${u}-star)" stroke="#ffd34d" stroke-width="1.6" stroke-linejoin="round"/>
      <ellipse cx="${(x - R * 0.28).toFixed(2)}" cy="${(y - R * 0.3).toFixed(2)}" rx="${(R * 0.22).toFixed(2)}" ry="${(R * 0.13).toFixed(2)}" transform="rotate(-35 ${(x - R * 0.28).toFixed(2)} ${(y - R * 0.3).toFixed(2)})" fill="#fff" opacity=".85"/>
    </g>`);
  }
  return `<g filter="url(#${u}-ds)">${out.join("")}</g>`;
}

function badgeSVGLegacy(b, u, standalone = false) {
  const sh = validHex(b.shield, "#7d6cf2"), rb = validHex(b.ribbon, "#f23d9c");
  const S = {
    ext: adj(sh, -0.22, 0.02), rimA: adj(sh, 0.07), rimB: adj(sh, -0.1, 0.05),
    faceA: adj(sh, 0.19, -0.02), faceB: adj(sh, 0.03), seam: adj(sh, -0.13, 0.05), line: adj(sh, 0.3)
  };
  const R = {
    top: adj(rb, 0.1), mid: rb, bot: adj(rb, -0.08, 0.04), tailA: adj(rb, -0.04), tailB: adj(rb, -0.15, 0.04),
    fold: adj(rb, -0.27, 0.05), text: adj(rb, -0.3, 0.08), hi: adj(rb, 0.3)
  };
  const icon = ICONS[b.icon] || ICONS.penal;
  const text = (b.text || "").trim();
  const fs = fitSize(text);
  const outer = roundPoly(hexPts(120, 106, 86), 14);
  const ext = roundPoly(hexPts(120, 114, 86), 14);
  const face = roundPoly(hexPts(120, 106, 71), 10);
  const inner = roundPoly(hexPts(120, 106, 64), 8);
  // ponta da faixa: tira com corte em V, girada para baixo e para fora a partir de trás da extremidade
  const tail = "M-39,-16 L20,-16 L20,16 L-39,16 L-26,0 Z";
  const tailG = `<g transform="translate(48 163) rotate(-60)"><path d="${tail}" fill="url(#${u}-tail)" stroke="${R.tailB}" stroke-width="2.4" stroke-linejoin="round"/>
      <path d="M-28,-9.4 L12,-9.4" stroke="${R.hi}" stroke-width="1.8" stroke-linecap="round" opacity=".35"/></g>`;
  const fontCss = standalone ? `<style>@font-face{font-family:"Fredoka";font-weight:600;src:url(data:font/woff2;base64,${FONT_B64}) format("woff2");}</style>` : "";
  const sizeAttr = standalone ? ' width="1024" height="1024"' : "";
  return `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 240 240"${sizeAttr}>${fontCss}
  <defs>
    <linearGradient id="${u}-rim" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="${S.rimA}"/><stop offset="1" stop-color="${S.rimB}"/></linearGradient>
    <linearGradient id="${u}-face" x1=".15" y1="0" x2=".85" y2="1"><stop offset="0" stop-color="${S.faceA}"/><stop offset="1" stop-color="${S.faceB}"/></linearGradient>
    <linearGradient id="${u}-edge" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#fff" stop-opacity=".75"/><stop offset=".55" stop-color="#fff" stop-opacity="0"/></linearGradient>
    <linearGradient id="${u}-band" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="${R.top}"/><stop offset=".5" stop-color="${R.mid}"/><stop offset="1" stop-color="${R.bot}"/></linearGradient>
    <linearGradient id="${u}-tail" x1="1" y1="0" x2="0" y2="0"><stop offset="0" stop-color="${R.fold}"/><stop offset=".42" stop-color="${R.tailB}"/><stop offset="1" stop-color="${R.tailA}"/></linearGradient>
    <clipPath id="${u}-fc"><path d="${face}"/></clipPath>
    <filter id="${u}-bs" x="-20%" y="-20%" width="140%" height="150%"><feDropShadow dx="0" dy="7" stdDeviation="6" flood-color="#07062a" flood-opacity=".45"/></filter>
    <filter id="${u}-ds" x="-25%" y="-25%" width="150%" height="160%"><feDropShadow dx="0" dy="3" stdDeviation="2.6" flood-color="#0b0a33" flood-opacity=".33"/></filter>
    <path id="${u}-tp" d="M18,151 Q120,171 222,151"/>
    <linearGradient id="${u}-star" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#fff3a6"/><stop offset=".45" stop-color="#ffcb1f"/><stop offset="1" stop-color="#f59a0c"/></linearGradient>
  </defs>
  <g filter="url(#${u}-bs)">
    <path d="${ext}" fill="${S.ext}"/>
    <path d="${outer}" fill="url(#${u}-rim)"/>
    <path d="${outer}" fill="none" stroke="url(#${u}-edge)" stroke-width="2.2"/>
    <path d="${face}" fill="url(#${u}-face)" stroke="${S.seam}" stroke-width="1.6" stroke-opacity=".55"/>
    <g clip-path="url(#${u}-fc)">
      <path d="M30,178 L150,20 L178,20 L58,178 Z" fill="#fff" opacity=".13"/>
      <path d="M72,190 L196,36 L206,36 L82,190 Z" fill="#fff" opacity=".09"/>
    </g>
    <path d="${inner}" fill="none" stroke="${S.line}" stroke-width="1.5" stroke-opacity=".55"/>
    ${sparkle(66, 58, 6, .9)}${sparkle(79, 47, 3.2, .7)}
    ${icon.draw(u)}
    ${tailG}
    <g transform="translate(240 0) scale(-1 1)">${tailG}</g>
    <path d="M32,135 Q120,156 208,135 L208,166 Q120,187 32,166 Z" fill="url(#${u}-band)" stroke="${R.bot}" stroke-width="2.4" stroke-linejoin="round" filter="url(#${u}-ds)"/>
    <path d="M39,140.6 Q120,159.8 201,140.6" fill="none" stroke="${R.hi}" stroke-width="2.2" stroke-linecap="round" opacity=".75"/>
    <path d="M39,161.5 Q120,180.7 201,161.5" fill="none" stroke="${R.fold}" stroke-width="1.6" stroke-linecap="round" opacity=".28"/>
    <g font-family="Fredoka, sans-serif" font-weight="600" font-size="${fs}" letter-spacing=".4" text-anchor="middle">
      <text dy=".36em" fill="${R.hi}" opacity=".55" transform="translate(0 1.2)"><textPath href="#${u}-tp" startOffset="50%">${esc(text)}</textPath></text>
      <text dy=".36em" fill="${R.text}"><textPath href="#${u}-tp" startOffset="50%">${esc(text)}</textPath></text>
    </g>
    ${starsSVG(u, clamp(parseInt(b.stars, 10) || 0, 0, 5))}
  </g>
</svg>`;
}

/* ---------- Estilo chapado (flat), alinhado às ilustrações do site ---------- */
function lum(hex) {
  const v = [1, 3, 5].map(i => parseInt(hex.slice(i, i + 2), 16) / 255).map(c => c <= .03928 ? c / 12.92 : Math.pow((c + .055) / 1.055, 2.4));
  return .2126 * v[0] + .7152 * v[1] + .0722 * v[2];
}
function illusConstFlat(u) {
  const W = "#F6F7FB", S1 = "#DCE1EC", S2 = "#C3CADB", NV = "#262245";
  const tower = x => `<rect x="${x}" y="20" width="14" height="80" rx="1.5" fill="${W}"/>
    <rect x="${x + 9.5}" y="20" width="4.5" height="80" fill="${S1}"/>
    ${Array.from({ length: 12 }, (_, i) => `<rect x="${x + 2.5}" y="${25.2 + i * 6}" width="6" height="1.6" rx=".8" fill="${S2}"/>`).join("")}`;
  return `
    <rect x="195.2" y="24" width="3.4" height="96" rx="1.7" fill="${S1}"/>
    <circle cx="196.9" cy="23" r="3" fill="#FFC533"/>
    <g transform="translate(198.6 27)">
      <path d="M0,0 Q9,-4 18,0 T36,0 L36,23 Q27,19 18,23 T0,23 Z" fill="#2FA35B"/>
      <path d="M18,3.6 L32,11.5 L18,19.4 L4,11.5 Z" fill="#FFC533"/>
      <circle cx="18" cy="11.5" r="5" fill="#2F4FB5"/>
      <path d="M13.2,10.7 Q18,9 22.8,12.3 L22.6,13.6 Q18,10.6 13.4,12 Z" fill="${W}"/>
    </g>
    ${tower(103)}${tower(123)}
    <rect x="116" y="46" width="8" height="9" fill="${S1}"/>
    <rect x="46" y="118" width="148" height="15" rx="2" fill="${S1}"/>
    ${Array.from({ length: 11 }, (_, i) => `<rect x="${54 + i * 12.6}" y="122.5" width="7" height="7" rx="1.4" fill="${S2}"/>`).join("")}
    <rect x="36" y="100" width="168" height="19" rx="3" fill="${W}"/>
    <rect x="36" y="113" width="168" height="6" rx="0" fill="${S1}"/>
    <path d="M47,100.5 A27,22 0 0 1 101,100.5 Z" fill="${W}"/>
    <path d="M74,78.5 A27,22 0 0 1 101,100.5 L74,100.5 Z" fill="${S1}"/>
    <path d="M138,78 Q140,100.5 166,100.5 Q192,100.5 194,78 Z" fill="${W}"/>
    <path d="M166,100.5 Q192,100.5 194,78 L166,78 Z" fill="${S1}"/>
    <ellipse cx="166" cy="78" rx="28" ry="5.4" fill="${S2}"/>
    <ellipse cx="166" cy="79.2" rx="23" ry="3.2" fill="#AEB6CB"/>
`;
}
function badgeSVG(b, u, standalone = false) {
  const sh = validHex(b.shield, "#2E9D94"), rb = validHex(b.ribbon, "#9B5CF6");
  const S = { rim: sh, face: adj(sh, 0.06, -0.02), base: adj(sh, -0.14, 0.02) };
  const R = { band: rb, tail: adj(rb, -0.1, -0.12), fold: adj(rb, -0.18, -0.12) };
  const txt = lum(rb) > 0.4 ? "#262245" : "#FFFFFF";
  const text = (b.text || "").trim(), fs = fitSize(text);
  const outer = roundPoly(hexPts(120, 106, 86), 14), ext = roundPoly(hexPts(120, 113, 86), 14), face = roundPoly(hexPts(120, 106, 70), 10);
  const tail = "M-39,-16 L20,-16 L20,16 L-39,16 L-26,0 Z";
  const tailG = `<g transform="translate(48 163) rotate(-60)"><path d="${tail}" fill="${R.tail}"/></g>`;
  const n = clamp(parseInt(b.stars, 10) || 0, 0, 5);
  let stars = "";
  for (let i = 0; i < n; i++) {
    const x = 120 + (i - (n - 1) / 2) * 23, dx = (x - 120) / 42, y = 194 - dx * dx * 7;
    const big = n % 2 === 1 && i === (n - 1) / 2, R2 = big ? 11 : 9.6;
    stars += `<path d="${starPath(x, y, R2)}" fill="#FFC533" stroke="#FFC533" stroke-width="2.4" stroke-linejoin="round"/>`;
  }
  const flatIcon = FLAT_ICONS[b.icon];
  const legacy = ICONS[b.icon] || ICONS.penal;
  const fontCss = standalone ? `<style>@font-face{font-family:"Fredoka";font-weight:600;src:url(data:font/woff2;base64,${FONT_B64}) format("woff2");}</style>` : "";
  const sizeAttr = standalone ? ' width="1024" height="1024"' : "";
  return `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 240 240"${sizeAttr}>${fontCss}
    <defs><path id="${u}-tp" d="M18,151 Q120,171 222,151"/>
      <filter id="${u}-ds" x="-25%" y="-25%" width="150%" height="160%"><feDropShadow dx="0" dy="3" stdDeviation="2.6" flood-color="#0b0a33" flood-opacity=".33"/></filter></defs>
    <path d="${ext}" fill="${S.base}"/>
    <path d="${outer}" fill="${S.rim}"/>
    <path d="${face}" fill="${S.face}"/>
    ${flatIcon ? flatIcon(u) : legacy.draw(u)}
    ${tailG}<g transform="translate(240 0) scale(-1 1)">${tailG}</g>
    <path d="M32,166 L52,170.6 L32,171.5 Z" fill="${R.fold}"/>
    <path d="M208,166 L188,170.6 L208,171.5 Z" fill="${R.fold}"/>
    <path d="M32,135 Q120,156 208,135 L208,166 Q120,187 32,166 Z" fill="${R.band}"/>
    <text font-family="Fredoka, sans-serif" font-weight="600" font-size="${fs}" letter-spacing=".4" text-anchor="middle" dy=".36em" fill="${txt}"><textPath href="#${u}-tp" startOffset="50%">${esc(text)}</textPath></text>
    ${stars}
  </svg>`;
}

/* Paleta chapada comum */
const F = { W: "#F6F7FB", S1: "#DCE1EC", S2: "#C3CADB", S3: "#A9B2C8", NV: "#262245", Y: "#FFC533", R: "#E8505B", RD: "#B83A47", G: "#2FA35B", GD: "#23864A" };

function illusPenalFlat(u) {
  // Cela: janela de pedra com grades, céu noturno com lua, e algema presa numa barra
  const arch = (x0, x1, top, bot) => { const r = (x1 - x0) / 2, cy = top + r; return `M${x0},${bot} L${x0},${cy} A${r},${r} 0 0 1 ${x1},${cy} L${x1},${bot} Z`; };
  const bars = [97, 112.2, 127.4, 142.6];
  return `
    <defs><clipPath id="${u}-win"><path d="${arch(82, 158, 36, 116)}"/></clipPath></defs>
    <g transform="translate(0 16)">
    <path d="${arch(70, 170, 24, 118)}" fill="${F.S1}"/>
    <path d="M120,24 A50,50 0 0 1 170,74 L170,118 L158,118 L158,74 A38,38 0 0 0 120,36 Z" fill="${F.S2}"/>
    <g fill="${F.S2}">
      <rect x="70" y="92" width="12" height="3"/><rect x="70" y="106" width="12" height="3"/>
      <rect x="158" y="86" width="12" height="3" fill="${F.S3}"/><rect x="158" y="102" width="12" height="3" fill="${F.S3}"/>
    </g>
    ${[150, 120, 90, 60, 30].map(d => { const r = d * Math.PI / 180, c = Math.cos(r), sn = Math.sin(r); return `<line x1="${(120 + 38 * c).toFixed(1)}" y1="${(74 - 38 * sn).toFixed(1)}" x2="${(120 + 50 * c).toFixed(1)}" y2="${(74 - 50 * sn).toFixed(1)}" stroke="${d > 90 ? F.S2 : F.S3}" stroke-width="2.6"/>`; }).join("")}
    <path d="${arch(82, 158, 36, 116)}" fill="${F.S3}"/>
    <g clip-path="url(#${u}-win)">
      <path d="${arch(87, 163, 41, 121)}" fill="${F.NV}"/>
      <circle cx="117" cy="55" r="11" fill="${F.Y}"/>
      <circle cx="122.5" cy="51" r="9.6" fill="${F.NV}"/>
      ${[[138, 50, 2.2], [150, 66, 1.6], [122, 47, 1.4], [146, 88, 1.3], [96, 82, 1.5]].map(([x, y, r]) => `<circle cx="${x}" cy="${y}" r="${r}" fill="${F.Y}"/>`).join("")}
      ${bars.map(x => `<rect x="${x - 3.2}" y="30" width="6.4" height="90" fill="${F.S1}"/><rect x="${x}" y="30" width="3.2" height="90" fill="${F.S2}"/>`).join("")}
      <rect x="80" y="70" width="80" height="5.5" fill="${F.S1}"/><rect x="80" y="73" width="80" height="2.5" fill="${F.S2}"/>
    </g>
    <rect x="62" y="113" width="116" height="9" rx="2" fill="${F.W}"/>
    <rect x="62" y="119" width="116" height="5" rx="2" fill="${F.S1}"/>
    <g transform="translate(0 -16)">
    <ellipse cx="142.6" cy="82" rx="11" ry="4" fill="none" stroke="${F.S3}" stroke-width="4.2"/>
    <rect x="139.4" y="76" width="6.4" height="12" fill="${F.S1}"/><rect x="142.6" y="76" width="3.2" height="12" fill="${F.S2}"/>
    <path d="M131.6,82 A11,4 0 0 0 153.6,82" fill="none" stroke="${F.W}" stroke-width="4.2" stroke-linecap="round"/>
    <rect x="138.6" y="84.4" width="8" height="8.6" rx="2.4" fill="${F.S2}"/>
    <circle cx="142.6" cy="88.4" r="1.3" fill="${F.NV}"/>
    ${[[96, 2.2, 3], [100.8, 1, 3], [105.6, 2.2, 3]].map(([y, rx, ry]) => `<ellipse cx="142.6" cy="${y}" rx="${rx}" ry="${ry}" fill="none" stroke="${F.S3}" stroke-width="1.9"/>`).join("")}
    <rect x="138.1" y="108.4" width="9" height="9" rx="2.6" fill="${F.S2}"/>
    <circle cx="142.6" cy="112.4" r="1.3" fill="${F.NV}"/>
    <circle cx="142.6" cy="127.4" r="11" fill="none" stroke="${F.W}" stroke-width="5.8"/>
    <path d="M153.6,127.4 A11,11 0 0 1 134.8,135.2" fill="none" stroke="${F.S2}" stroke-width="5.8"/>
    </g>
    </g>
  `;
}

function illusCivilFlat(u) {
  // Contrato assinado com lacre + caneta-tinteiro, chapado
  const lumps = Array.from({ length: 10 }, (_, i) => {
    const a = i * 36 * Math.PI / 180; return `<circle cx="${(142 + 12.6 * Math.cos(a)).toFixed(1)}" cy="${(120 + 12.6 * Math.sin(a)).toFixed(1)}" r="4.6"/>`;
  }).join("");
  const paper = "M70,38 H138 L158,58 V134 Q158,140 152,140 H70 Q64,140 64,134 V44 Q64,38 70,38 Z";
  return `
    <g transform="rotate(5 112 90)"><path d="${paper}" fill="${F.S1}"/></g>
    <g transform="rotate(-9 112 90)">
      <path d="${paper}" fill="#FFFFFF"/>
      <path d="M138,38 L138,52 Q138,58 144,58 L158,58 Z" fill="${F.S1}"/>
      <rect x="74" y="50" width="44" height="6.5" rx="3.2" fill="${F.NV}"/>
      <rect x="74" y="65" width="74" height="4" rx="2" fill="${F.S1}"/>
      <rect x="74" y="74" width="66" height="4" rx="2" fill="${F.S1}"/>
      <rect x="74" y="83" width="74" height="4" rx="2" fill="${F.S1}"/>
      <rect x="74" y="92" width="48" height="4" rx="2" fill="${F.S1}"/>
      <rect x="74" y="120" width="48" height="1.8" rx=".9" fill="${F.S2}"/>
      <path d="M76,116 c5,-11 10,-9 8,-1 s6,-12 10,-6 s3,7 9,-2 s7,-4 15,1" fill="none" stroke="#4C7BE8" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/>
      <g fill="${F.R}">${lumps}<circle cx="142" cy="120" r="13.5"/></g>
      <circle cx="142" cy="120" r="9" fill="${F.RD}"/>
      <circle cx="142" cy="120" r="7" fill="${F.R}"/>
      <text x="142" y="120" dy=".36em" text-anchor="middle" font-family="Fredoka, sans-serif" font-weight="600" font-size="10" fill="${F.RD}">§</text>
    </g>
    <g transform="translate(117 109) rotate(-48)">
      <path d="M0,0 L18,-6.8 Q22.5,0 18,6.8 Z" fill="${F.Y}"/>
      <path d="M0,0 L18,0 L18,6.8 Q22.5,0 18,6.8 Z M0,0 L18,6.8 L18,0 Z" fill="#E5A91F"/>
      <rect x="2.5" y="-.6" width="11.5" height="1.2" rx=".6" fill="#9A6A10"/>
      <circle cx="14.5" cy="0" r="1.5" fill="#9A6A10"/>
      <rect x="17" y="-8" width="16" height="16" rx="3" fill="${F.NV}"/>
      <rect x="35" y="-10.5" width="85" height="21" rx="10.5" fill="#4C7BE8"/>
      <path d="M35,0 L120,0 L120,0 A10.5,10.5 0 0 1 109.5,10.5 L45.5,10.5 A10.5,10.5 0 0 1 35,0 Z" fill="#3A63C8"/>
      <rect x="31" y="-10.5" width="6" height="21" rx="2" fill="${F.Y}"/>
      <rect x="80" y="-11" width="6" height="22" rx="2" fill="${F.Y}"/>
      <rect x="86" y="-14" width="27" height="4.2" rx="2.1" fill="${F.Y}"/>
      <circle cx="111" cy="-11.6" r="2.8" fill="${F.Y}"/>
      <ellipse cx="118.5" cy="0" rx="2.2" ry="7" fill="#E5A91F"/>
    </g>`;
}

function illusConsumidorFlat(u) {
  // Sacola de compras + etiqueta + escudo do CDC, chapado
  const shield = "M0,-29 Q14,-23 27,-25 L27,1 Q27,21 0,31 Q-27,21 -27,1 L-27,-25 Q-14,-23 0,-29 Z";
  const inner = "M0,-24 Q11.5,-19 22.5,-20.6 L22.5,1 Q22.5,17.4 0,25.8 Q-22.5,17.4 -22.5,1 L-22.5,-20.6 Q-11.5,-19 0,-24 Z";
  const BAG = "#E0805F", BAGD = "#C4664A", BAGI = "#8E4A36";
  return `
    <path d="M88,59 C88,24 100,22 106,22 C114,22 124,26 124,59" fill="none" stroke="${BAGI}" stroke-width="5" stroke-linecap="round"/>
    <path d="M60,64 L80,57 L148,57 L128,64 Z" fill="${BAGI}"/>
    <path d="M128,64 L148,57 L148,139 L128,144 Z" fill="${BAGD}"/>
    <path d="M128,64 L148,57 L138,74 Z" fill="#A9533B"/>
    <rect x="136.8" y="74" width="2.4" height="67" fill="#B05A40"/>
    <rect x="60" y="64" width="68" height="80" rx="3" fill="${BAG}"/>
    <g transform="translate(94 104)" fill="none" stroke="#FFFFFF" stroke-width="3.6" stroke-linecap="round" stroke-linejoin="round">
      <path d="M-17,-13 L-11,-13 L-6,6 L13,6 L17,-7 L-9,-7"/>
      <circle cx="-3" cy="13" r="2.8" fill="#FFFFFF" stroke="none"/><circle cx="10" cy="13" r="2.8" fill="#FFFFFF" stroke="none"/>
    </g>
    <path d="M76,66 C76,29 88,27 94,27 C102,27 112,31 112,66" fill="none" stroke="#A9533B" stroke-width="5" stroke-linecap="round"/>
    <circle cx="76" cy="69" r="2.4" fill="${BAGI}"/><circle cx="112" cy="69" r="2.4" fill="${BAGI}"/>
    <path d="M76,70 Q66,76 57,83" fill="none" stroke="${F.NV}" stroke-width="1.4"/>
    <g transform="translate(52 94) rotate(-62)">
      <path d="M-12,-9 L10,-9 Q14,-9 14,-5 L14,5 Q14,9 10,9 L-12,9 L-20,0 Z" fill="${F.Y}"/>
      <path d="M-20,0 L14,0 L14,5 Q14,9 10,9 L-12,9 Z" fill="#F2B21E"/>
      <circle cx="-13" cy="0" r="2.4" fill="${F.W}"/>
      <text x="2" y="0" dy=".36em" text-anchor="middle" font-family="Fredoka, sans-serif" font-weight="600" font-size="9.5" fill="#8A5A0A">R$</text>
    </g>
    <g transform="translate(157 110) rotate(9)">
      <path d="${shield}" fill="${F.Y}"/>
      <path d="${inner}" fill="#4C7BE8"/>
      <path d="M0,-24 Q11.5,-19 22.5,-20.6 L22.5,1 Q22.5,17.4 0,25.8 Z" fill="#3A63C8"/>
      <path d="M-11,0 L-3,8.5 L12,-8" fill="none" stroke="#FFFFFF" stroke-width="7" stroke-linecap="round" stroke-linejoin="round"/>
    </g>`;
}

function coinStackFlat(x, baseY, n) {
  let g = "";
  for (let i = 0; i < n; i++) {
    const y = baseY - i * 6.2;
    g += `<ellipse cx="${x}" cy="${y + 5}" rx="19" ry="6.6" fill="#E09A12"/>
      <rect x="${x - 19}" y="${y}" width="38" height="5" fill="#F2B21E"/>
      <rect x="${x + 4}" y="${y}" width="15" height="5" fill="#E8A516"/>
      <ellipse cx="${x}" cy="${y}" rx="19" ry="6.6" fill="${F.Y}"/>`;
  }
  const top = baseY - (n - 1) * 6.2;
  g += `<ellipse cx="${x}" cy="${top}" rx="13" ry="4.2" fill="#F7BB2A"/>
    <g transform="translate(${x} ${top}) scale(1 .38)"><text x="0" y="0" dy=".36em" text-anchor="middle" font-family="Fredoka, sans-serif" font-weight="600" font-size="17" fill="#B9800C">R$</text></g>`;
  return g;
}
function illusTributarioFlat(u) {
  // Calculadora com 27,5% + pilhas de moedas, chapado
  const keys = [];
  for (let r = 0; r < 4; r++) for (let c = 0; c < 4; c++) {
    const op = c === 3, eq = r === 3 && c === 3;
    const top = eq ? F.G : op ? "#FF9F43" : F.W, base = eq ? F.GD : op ? "#E0802A" : F.S2;
    keys.push(`<rect x="${-24 + c * 13}" y="${-4 + r * 11.5}" width="10.5" height="9" rx="2.4" fill="${base}"/>
      <rect x="${-24 + c * 13}" y="${-4 + r * 11.5}" width="10.5" height="7" rx="2.4" fill="${top}"/>`);
  }
  return `
    <g transform="translate(146 74) rotate(13)">
      <rect x="-31" y="-46" width="64" height="96" rx="9" fill="#1C1938" transform="translate(3 3)"/>
      <rect x="-31" y="-46" width="64" height="96" rx="9" fill="#34306A"/>
      <rect x="-25" y="-36" width="52" height="25" rx="4" fill="#1C1938"/>
      <rect x="-23" y="-34" width="48" height="21" rx="3" fill="#BFE8C8"/>
      <text x="22" y="-23.5" dy=".36em" text-anchor="end" font-family="Fredoka, sans-serif" font-weight="600" font-size="14" fill="#245A3A">27,5%</text>
      ${keys.join("")}
    </g>
    ${coinStackFlat(116, 132, 4)}
    ${coinStackFlat(80, 128, 7)}`;
}
const FLAT_ICONS = { const: illusConstFlat, penal: illusPenalFlat, civil: illusCivilFlat, consumidor: illusConsumidorFlat, tributario: illusTributarioFlat };

function illusProcessoFlat(u) {
  // Autos do processo + martelo do juiz com base, chapado
  const folderPath = "M-32,-36 L-12,-36 L-8,-41 L8,-41 L12,-36 L32,-36 Q35,-36 35,-33 L35,40 Q35,43 32,43 L-32,43 Q-35,43 -35,40 L-35,-33 Q-35,-36 -32,-36 Z";
  const WD = "#B5713F", WDD = "#8E5530", WDL = "#C98A55";
  return `
    <g transform="translate(98 102) rotate(-3)">
      <rect x="-28" y="-44" width="56" height="70" rx="2" fill="${F.S1}" transform="rotate(4)"/>
      <rect x="-29" y="-40" width="56" height="70" rx="2" fill="${F.W}"/>
      <path d="${folderPath}" fill="#F2C66D"/>
      <path d="M-35,22 L35,22 L35,40 Q35,43 32,43 L-32,43 Q-35,43 -35,40 Z" fill="#E3AF52"/>
      <rect x="-22" y="-20" width="44" height="30" rx="3" fill="${F.W}"/>
      <text x="0" y="-12" dy=".36em" text-anchor="middle" font-family="Fredoka, sans-serif" font-weight="600" font-size="9.5" letter-spacing=".8" fill="${F.NV}">AUTOS</text>
      <rect x="-15" y="-1" width="30" height="3" rx="1.5" fill="${F.S1}"/>
      <rect x="-15" y="4.5" width="20" height="3" rx="1.5" fill="${F.S1}"/>
      <g transform="translate(0 -40)">
        <path d="M-7,-5 L-5,-15 L5,-15 L7,-5" fill="none" stroke="${F.S2}" stroke-width="2.4" stroke-linejoin="round"/>
        <rect x="-10" y="-5" width="20" height="10" rx="2.2" fill="${F.NV}"/>
      </g>
    </g>
    <ellipse cx="150" cy="134" rx="27" ry="8" fill="${WDD}"/>
    <rect x="123" y="124" width="54" height="10" fill="${WDD}"/>
    <ellipse cx="150" cy="124" rx="27" ry="8" fill="${WD}"/>
    <ellipse cx="150" cy="124" rx="18" ry="5" fill="#A3653A"/>
    <g transform="translate(146 98) rotate(38)">
      <rect x="-4.6" y="-88" width="9.2" height="80" rx="4.6" fill="${WD}"/>
      <rect x="0" y="-86" width="4.6" height="78" rx="2.3" fill="${WDD}"/>
      <ellipse cx="0" cy="-88" rx="6.4" ry="4.4" fill="${WDL}"/>
      <rect x="-6" y="-22" width="12" height="5" rx="1.6" fill="${F.Y}"/>
      <rect x="-27" y="-12" width="54" height="24" rx="7" fill="${WD}"/>
      <rect x="-27" y="2" width="54" height="10" rx="5" fill="${WDD}"/>
      <rect x="-18" y="-12.6" width="5.5" height="25.2" rx="1.2" fill="${F.Y}"/>
      <rect x="12.5" y="-12.6" width="5.5" height="25.2" rx="1.2" fill="${F.Y}"/>
      <rect x="-18" y="2" width="5.5" height="10.6" rx="1.2" fill="#F2B21E"/>
      <rect x="12.5" y="2" width="5.5" height="10.6" rx="1.2" fill="#F2B21E"/>
      <ellipse cx="-27" cy="0" rx="4" ry="11.6" fill="${WDL}"/>
      <ellipse cx="27" cy="0" rx="4" ry="11.6" fill="#7A4524"/>
    </g>`;
}
FLAT_ICONS.processo = illusProcessoFlat;

function illusEstatutoFlat(u) {
  // Pasta executiva com balança + Estatuto da Advocacia, chapado
  const LB = "#A9653A", LD = "#8E5530", LDD = "#6E3F22";
  return `
    <g transform="translate(138 90) scale(.86) rotate(7) translate(-140 -96)">
      <path d="M118,58 L118,42 Q118,34 126,34 L154,34 Q162,34 162,42 L162,58" fill="none" stroke="${LDD}" stroke-width="7.4" stroke-linecap="round"/>
      <rect x="84" y="59" width="112" height="82" rx="10" fill="${LDD}"/>
      <rect x="84" y="56" width="112" height="82" rx="10" fill="${LD}"/>
      <path d="M84,66 Q84,56 94,56 L186,56 Q196,56 196,66 L196,84 Q140,94 84,84 Z" fill="${LB}"/>
      <path d="M90,92 Q140,102 190,92" fill="none" stroke="${LDD}" stroke-width="1.6" stroke-dasharray="3.4 3"/>
      <rect x="104" y="80" width="12" height="13" rx="2.4" fill="${F.Y}"/>
      <rect x="164" y="80" width="12" height="13" rx="2.4" fill="${F.Y}"/>
      <rect x="107.5" y="85" width="5" height="2.4" rx="1.2" fill="#B9800C"/><rect x="167.5" y="85" width="5" height="2.4" rx="1.2" fill="#B9800C"/>
      <g transform="translate(140 116)" fill="none" stroke="${F.Y}" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round">
        <path d="M0,-14 L0,12 M-8,12 L8,12 M-17,-9 L17,-9"/>
        <circle cx="0" cy="-15.5" r="2.2" fill="${F.Y}" stroke="none"/>
        <path d="M-17,-9 L-22,2 M-17,-9 L-12,2 M17,-9 L12,2 M17,-9 L22,2"/>
        <path d="M-23,2 Q-17,8 -11,2 Z M11,2 Q17,8 23,2 Z" fill="${F.Y}"/>
      </g>
    </g>
    <g transform="translate(78 108) rotate(-10) scale(.74)">
      <rect x="-23" y="-37" width="52" height="72" rx="5" fill="#2E4FAE"/>
      <rect x="-22" y="-34" width="48" height="66" rx="2" fill="${F.W}"/>
      <rect x="18" y="-32" width="3" height="62" fill="#E6E2D6"/>
      <rect x="-30" y="-38" width="48" height="72" rx="5" fill="#4C7BE8"/>
      <rect x="-30" y="-38" width="9" height="72" rx="4.5" fill="#3A63C8"/>
      <rect x="-17" y="-31" width="30" height="58" rx="3" fill="none" stroke="${F.Y}" stroke-width="1.6"/>
      <path d="${starPath(-2, -19, 5.8)}" fill="${F.Y}"/>
      <text x="-2" y="-3" dy=".36em" text-anchor="middle" font-family="Fredoka, sans-serif" font-weight="600" font-size="5.3" fill="${F.Y}">ESTATUTO</text>
      <rect x="-12" y="4" width="20" height="1.6" rx=".8" fill="${F.Y}"/>
      <text x="-2" y="13" dy=".36em" text-anchor="middle" font-family="Fredoka, sans-serif" font-weight="600" font-size="6.6" fill="${F.W}">8.906/94</text>
    </g>`;
}
FLAT_ICONS.estatuto = illusEstatutoFlat;

function illusPrevidenciaFlat(u) {
  // Cofrinho recebendo moeda + ampulheta, chapado
  const P = "#F4A3BA", PD = "#E07F9E", PDD = "#C9668A", PL = "#FAC6D5", PN = "#B9506F";
  const WD = "#B5713F", WDD = "#8E5530";
  return `
    <defs><clipPath id="${u}-pigc"><ellipse cx="114" cy="92" rx="45" ry="33"/></clipPath></defs>
    <g transform="translate(0 13)">
    ${[[79, 126.5], [119, 128]].map(([x, b]) => `<path d="M${x},106 L${x + .8},${b - 4} Q${x + .8},${b} ${x + 7.5},${b} Q${x + 14.2},${b} ${x + 14.2},${b - 4} L${x + 15},106 Z" fill="${PDD}"/>`).join("")}
    ${[[85, 131], [125, 132.5]].map(([x, b]) => `<path d="M${x},106 L${x + 1},${b - 4.5} Q${x + 1},${b} ${x + 8.5},${b} Q${x + 16},${b} ${x + 16},${b - 4.5} L${x + 17},106 Z" fill="${PD}"/>
      <path d="M${x + 9},106 L${x + 17},106 L${x + 16},${b - 4.5} Q${x + 16},${b} ${x + 9},${b} Z" fill="${PDD}"/>`).join("")}
    <path d="M155,86 q11,-3 10,-11 q-1,-6 -7,-3 q-4,3 1,7" fill="none" stroke="${PD}" stroke-width="3.4" stroke-linecap="round"/>
    <path d="M80,72 Q74,52 82,46 Q92,50 96,64 Z" fill="${PDD}" transform="translate(88 68) scale(.76) translate(-88 -68)"/>
    <ellipse cx="114" cy="92" rx="45" ry="33" fill="${PD}"/>
    <g clip-path="url(#${u}-pigc)"><ellipse cx="108" cy="86" rx="45" ry="32" fill="${P}"/></g>
    <g transform="translate(106 65) scale(.76) translate(-106 -65)">
      <path d="M98,66 Q100,44 112,42 Q118,52 114,66 Z" fill="${PD}"/>
      <path d="M103,63 Q104.5,52 110,49.5 Q112.5,56 110.5,63 Q107,64.5 103,63 Z" fill="${PL}"/>
    </g>
    <ellipse cx="131" cy="66" rx="10" ry="2.6" fill="${PN}" transform="rotate(14 131 66)"/>
    <ellipse cx="72" cy="101" rx="9.5" ry="11.5" fill="${PDD}"/>
    <ellipse cx="68.5" cy="101" rx="9.5" ry="11.5" fill="${PL}"/>
    <ellipse cx="65.4" cy="101" rx="1.9" ry="3.6" fill="${PN}"/>
    <ellipse cx="71.6" cy="101" rx="1.9" ry="3.6" fill="${PN}"/>
    <ellipse cx="74" cy="84" rx="2.9" ry="3.8" fill="${F.NV}"/>
    <ellipse cx="91" cy="83" rx="3.4" ry="4.4" fill="${F.NV}"/>
    <ellipse cx="94" cy="98" rx="7" ry="4" fill="#F08BA8"/>
    <g transform="translate(8.4 -6)">
      <circle cx="122.6" cy="44.6" r="14" fill="#E09A12"/>
      <circle cx="122.6" cy="42.6" r="14" fill="${F.Y}"/>
      <circle cx="122.6" cy="42.6" r="10" fill="#F7BB2A"/>
      <text x="122.6" y="42.6" dy=".36em" text-anchor="middle" font-family="Fredoka, sans-serif" font-weight="600" font-size="10" fill="#B9800C">R$</text>
    </g>
    </g>
    <g transform="translate(166 108) rotate(10)">
      <path d="M-12,-22 Q-12,-6 -2,0 Q-12,6 -12,22 L12,22 Q12,6 2,0 Q12,-6 12,-22 Z" fill="#DCEFFA"/>
      <path d="M0,-22 L12,-22 Q12,-6 2,0 Q12,6 12,22 L0,22 Z" fill="#C4E0F2"/>
      <path d="M-8,-12 Q-6,-5 -1,-2 L1,-2 Q6,-5 8,-12 Z" fill="${F.Y}"/>
      <rect x="-.8" y="0" width="1.6" height="14" fill="#F2B21E"/>
      <path d="M-11,22 Q-9,12 0,11 Q9,12 11,22 Z" fill="${F.Y}"/>
      <path d="M0,11 Q9,12 11,22 L0,22 Z" fill="#F2B21E"/>
      <rect x="-15.5" y="-22" width="3.6" height="44" rx="1.8" fill="${WD}"/>
      <rect x="11.9" y="-22" width="3.6" height="44" rx="1.8" fill="${WDD}"/>
      <rect x="-17" y="-29" width="34" height="7" rx="3" fill="${WD}"/>
      <rect x="-17" y="-25" width="34" height="3" fill="${WDD}"/>
      <rect x="-17" y="22" width="34" height="7" rx="3" fill="${WD}"/>
      <rect x="-17" y="26" width="34" height="3" rx="1.5" fill="${WDD}"/>
    </g>`;
}
FLAT_ICONS.previdencia = illusPrevidenciaFlat;

function illusTrabalhoFlat(u) {
  // Capacete + chave de boca + Carteira de Trabalho, chapado
  const dome = "M-34,0 A34,33 0 0 1 34,0 A34,7.5 0 0 1 -34,0 Z";
  const Y2 = "#F2B21E", Y3 = "#E09A12";
  return `
    <defs><clipPath id="${u}-dome"><path d="${dome}"/></clipPath></defs>
    <g transform="translate(127 66) rotate(8) scale(1.22)">
      <ellipse cx="-3" cy="5" rx="48" ry="12.5" fill="${Y3}"/>
      <ellipse cx="-3" cy="2" rx="48" ry="12.5" fill="${Y2}"/>
      <path d="${dome}" fill="${F.Y}"/>
      <g clip-path="url(#${u}-dome)"><path d="M8,-40 L40,-40 L40,12 L8,12 Z" fill="${Y2}"/></g>
      <path d="M-7.5,-32 Q-8.4,-12 -8.6,6.6" fill="none" stroke="${Y2}" stroke-width="2.2" stroke-linecap="round"/>
      <path d="M7.5,-32 Q8.4,-12 8.6,6.6" fill="none" stroke="${Y3}" stroke-width="2.2" stroke-linecap="round"/>
    </g>
    <g transform="translate(138 103) rotate(36)">
      <rect x="-44" y="-5.6" width="68" height="11.2" rx="5.6" fill="${F.S1}"/>
      <path d="M-44,0 L24,0 L24,0 A5.6,5.6 0 0 1 18.4,5.6 L-38.4,5.6 A5.6,5.6 0 0 1 -44,0 Z" fill="${F.S2}"/>
      <path d="M20,-6.5 L24,-12 Q33,-16.5 42,-12 L46.5,-6 L37,-3.8 L37,3.8 L46.5,6 L42,12 Q33,16.5 24,12 L20,6.5 Z" fill="${F.S1}"/>
      <path d="M20,0 L37,0 L37,3.8 L46.5,6 L42,12 Q33,16.5 24,12 L20,6.5 Z" fill="${F.S2}"/>
      <circle cx="-36" cy="0" r="2.6" fill="${F.S3}"/>
    </g>
    <g transform="translate(101 106) rotate(-8) scale(1.04)">
      <rect x="-24" y="-34" width="51" height="70" rx="5" fill="#1E3A80"/>
      <rect x="-23" y="-33" width="48" height="66" rx="3" fill="${F.W}"/>
      <rect x="18" y="-31" width="3" height="62" fill="#E6E2D6"/>
      <rect x="-28" y="-36" width="47" height="70" rx="5.5" fill="#2E4FAE"/>
      <rect x="-28" y="-36" width="7" height="70" rx="3.5" fill="#243F8E"/>
      <text x="-3.5" y="-24" dy=".36em" text-anchor="middle" font-family="Fredoka, sans-serif" font-weight="600" font-size="5.6" letter-spacing=".2" fill="${F.Y}">CARTEIRA DE</text>
      <text x="-3.5" y="-16" dy=".36em" text-anchor="middle" font-family="Fredoka, sans-serif" font-weight="600" font-size="7.4" letter-spacing=".2" fill="${F.Y}">TRABALHO</text>
      <circle cx="-3.5" cy="4" r="10.5" fill="${F.Y}"/>
      <circle cx="-3.5" cy="4" r="8.2" fill="#243F8E"/>
      <path d="${starPath(-3.5, 4.6, 5.4)}" fill="${F.Y}"/>
      <rect x="-16" y="22" width="25" height="2" rx="1" fill="${F.Y}"/>
      <rect x="-12" y="26.4" width="17" height="1.8" rx=".9" fill="#E8C056"/>
    </g>`;
}
FLAT_ICONS.trabalho = illusTrabalhoFlat;

function illusAdministrativoFlat(u) {
  // Portaria com "DEFERIDO" + carimbo de madeira, chapado
  const WT = "#C98A55", WD = "#B5713F", WDD = "#8E5530";
  return `
    <defs><clipPath id="${u}-knob"><ellipse cx="4" cy="-35" rx="14" ry="12.5"/></clipPath></defs>
    <g transform="translate(-8 2) rotate(-6 98 100)">
      <rect x="62" y="56" width="72" height="88" rx="4" fill="${F.W}"/>
      <circle cx="98" cy="67" r="5.6" fill="${F.Y}"/>
      <path d="${starPath(98, 67.4, 3.4)}" fill="${F.W}"/>
      <text x="98" y="79" dy=".36em" text-anchor="middle" font-family="Fredoka, sans-serif" font-weight="600" font-size="7" letter-spacing=".6" fill="${F.NV}">PORTARIA</text>
      <rect x="72" y="88" width="52" height="3.4" rx="1.7" fill="${F.S1}"/>
      <rect x="72" y="95" width="46" height="3.4" rx="1.7" fill="${F.S1}"/>
      <rect x="72" y="102" width="52" height="3.4" rx="1.7" fill="${F.S1}"/>
      <rect x="72" y="109" width="30" height="3.4" rx="1.7" fill="${F.S1}"/>
      <g transform="translate(103 126) rotate(-11)">
        <rect x="-25" y="-9" width="50" height="18" rx="2.5" fill="none" stroke="${F.R}" stroke-width="2"/>
        <rect x="-21.6" y="-5.8" width="43.2" height="11.6" rx="1.5" fill="none" stroke="${F.R}" stroke-width=".9"/>
        <text x="0" y="0" dy=".36em" text-anchor="middle" font-family="Fredoka, sans-serif" font-weight="600" font-size="7.2" letter-spacing=".5" fill="${F.R}">DEFERIDO</text>
      </g>
    </g>
    <g transform="translate(151 72) rotate(14) scale(1.34)">
      <path d="M24,19 L32,13 L32,19 L24,25.5 Z" fill="${F.RD}"/>
      <rect x="-24" y="19" width="48" height="6.5" fill="${F.R}"/>
      <path d="M24,6 L32,0 L32,14 L24,20 Z" fill="${WDD}"/>
      <rect x="-24" y="6" width="48" height="14" fill="${WD}"/>
      <path d="M-24,6 L-16,0 L32,0 L24,6 Z" fill="${WT}"/>
      <ellipse cx="4" cy="3" rx="7.5" ry="2.4" fill="${WDD}"/>
      <rect x="-1.5" y="-26" width="11" height="29" rx="3" fill="${WD}"/>
      <rect x="4" y="-26" width="5.5" height="29" rx="2.5" fill="${WDD}"/>
      <ellipse cx="4" cy="-35" rx="14" ry="12.5" fill="${F.R}"/>
      <g clip-path="url(#${u}-knob)"><ellipse cx="9" cy="-31" rx="14" ry="12.5" fill="${F.RD}"/><ellipse cx="2" cy="-38" rx="14" ry="12.5" fill="${F.R}"/></g>
    </g>`;
}
FLAT_ICONS.administrativo = illusAdministrativoFlat;

function boxFlat(x0, x1, y0, y1, dx, dy, front, side, top) {
  return `<path d="M${x1},${y0} L${x1 + dx},${y0 + dy} L${x1 + dx},${y1 + dy} L${x1},${y1} Z" fill="${side}"/>
    <path d="M${x0},${y0} L${x0 + dx},${y0 + dy} L${x1 + dx},${y0 + dy} L${x1},${y0} Z" fill="${top}"/>
    <rect x="${x0}" y="${y0}" width="${x1 - x0}" height="${y1 - y0}" fill="${front}"/>`;
}
function illusEmpresarialFlat(u) {
  // Prédios + gráfico de barras + seta de crescimento, chapado
  const win = (x0, cols, y0, rows, w, h, gx, gy, lit, off) => {
    let s = "";
    for (let r = 0; r < rows; r++) for (let c = 0; c < cols; c++) {
      const on = lit.includes(r * cols + c);
      s += `<rect x="${x0 + c * (w + gx)}" y="${y0 + r * (h + gy)}" width="${w}" height="${h}" rx="1" fill="${on ? F.Y : off}"/>`;
    }
    return s;
  };
  return `<g transform="translate(9 0)">
    ${boxFlat(66, 98, 60, 136, 12, -6, "#8B7CE8", "#6F60CC", "#B8AEF3")}
    ${win(71, 3, 67, 7, 6, 5.4, 4, 4.4, [4, 9, 13], "#D9D3FA")}
    ${boxFlat(102, 144, 28, 136, 14, -7, "#4C7BE8", "#3A63C8", "#8FB0F2")}
    ${win(108, 4, 36, 10, 6, 5.6, 3.6, 4.4, [2, 7, 9, 18, 21, 27, 32], "#BFD4FA")}
    ${Array.from({ length: 10 }, (_, i) => `<path d="M147,${31.5 + i * 10} L155,${27.5 + i * 10} L155,${30 + i * 10} L147,${34 + i * 10} Z" fill="#5A80DC"/>`).join("")}
    ${boxFlat(56, 70, 116, 138, 7, -4, F.Y, "#E09A12", "#FCD65E")}
    ${boxFlat(76, 90, 102, 138, 7, -4, F.Y, "#E09A12", "#FCD65E")}
    ${boxFlat(96, 110, 86, 138, 7, -4, F.Y, "#E09A12", "#FCD65E")}
    <path d="M50,108 L84,88 L100,98 L150,60" fill="none" stroke="${F.G}" stroke-width="8" stroke-linecap="round" stroke-linejoin="round"/>
    <path d="M140.5,52.5 L163,49 L157.5,71 Z" fill="${F.G}" stroke="${F.G}" stroke-width="2.4" stroke-linejoin="round"/>
  </g>`;
}
FLAT_ICONS.empresarial = illusEmpresarialFlat;

export { ICONS, RIBBON_SWATCHES, SHIELD_SWATCHES, badgeSVG };
