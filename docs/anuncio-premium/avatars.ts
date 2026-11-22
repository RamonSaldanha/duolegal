import { buildAvatarSvg } from '../../resources/js/lib/avatar/index';
const base = { body: 'magro', hair: 'comprido', brows: 'cilios', mouth: 'sorriso', earrings: 'argola', glasses: 'nenhum', beard: 'nenhum', skin: '#C6694E', hairColor: '#241F1F', clothesColor: '#3FA0DE', earringColor: '#F2C219', glassesColor: '#2B2440', background: '#F6ECC8' } as any;
const out: Record<string, string> = {};
for (const o of ['nenhum', 'toga', 'astronauta']) out['ana_' + o] = buildAvatarSvg({ ...base, outfit: o }, { uid: 'a' + o } as any);
out.p1 = buildAvatarSvg({ ...base, hair: 'curto', brows: 'reta', earrings: 'nenhum', beard: 'grossa', glasses: 'grau', skin: '#F0B489', hairColor: '#3A2C22', clothesColor: '#3D4A7A', outfit: 'nenhum', background: '#DCE8F7' }, { crop: [36, 12, 128, 108] as any, uid: 'p1' } as any);
out.p2 = buildAvatarSvg({ ...base, hair: 'coque', skin: '#FBDCC2', hairColor: '#C4562E', clothesColor: '#C2455F', outfit: 'nenhum', background: '#F3DCE2' }, { crop: [36, 12, 128, 108] as any, uid: 'p2' } as any);
out.p3 = buildAvatarSvg({ ...base, hair: 'afro', brows: 'reta', earrings: 'nenhum', skin: '#8C4633', hairColor: '#241F1F', clothesColor: '#4F7A3D', outfit: 'nenhum', background: '#E2EFD9' }, { crop: [36, 12, 128, 108] as any, uid: 'p3' } as any);
out.me = buildAvatarSvg({ ...base, outfit: 'astronauta' }, { crop: [36, 12, 128, 108] as any, uid: 'me' } as any);
console.log(JSON.stringify(out));
