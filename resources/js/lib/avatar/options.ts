/**
 * Catálogo de opções do avatar.
 *
 * Base enxuta desta rodada: uma cabeça (sem escolha), dois corpos e uma roupa.
 * A variedade vem toda das paletas, que é onde ela rende mais por peça desenhada.
 *
 * Os ids estão amarrados ao código de desenho em `parts/` — acrescentar um id
 * aqui exige acrescentar o desenho lá e a mesma lista em `config/avatar.php`
 * (que valida o que chega do navegador).
 *
 * As cores não têm whitelist: o servidor só exige o formato `#rrggbb`. Estas
 * paletas são sugestões da interface, então dá para mexer nelas à vontade.
 */

import type { AvatarConfig, AvatarCrop, BodyShape, BrowStyle, HairStyle } from './types';

/**
 * Recorte das miniaturas do editor. Corpo e cabelo mudam a silhueta inteira,
 * então a miniatura mostra o avatar todo — enquadrar só um pedaço sobra vazio e
 * esconde justamente a comparação que a pessoa está fazendo.
 */
export const FULL_CROP: AvatarCrop = [0, 0, 200, 200];
/** Enquadra a cabeça, para as peças que só mudam a expressão. */
export const FACE_CROP: AvatarCrop = [36, 12, 128, 108];

export interface ShapeOption<T extends string> {
    id: T;
    label: string;
}

export const BODIES_OPTIONS: ShapeOption<BodyShape>[] = [
    { id: 'magro', label: 'Magro' },
    { id: 'gordo', label: 'Gordo' },
];

export const HAIRS: ShapeOption<HairStyle>[] = [
    { id: 'nenhum', label: 'Careca' },
    { id: 'comprido', label: 'Comprido' },
];

export const BROWS: ShapeOption<BrowStyle>[] = [
    { id: 'reta', label: 'Reta' },
    { id: 'arqueada', label: 'Arqueada' },
];

export const SKIN_COLORS = ['#FBDCC2', '#F5DCC0', '#EFC09A', '#F0B489', '#EE8B72', '#DD7B5E', '#C6694E', '#A9563F', '#8C4633', '#45291D'];

/** Cabelo e sobrancelha; a barba vai usar a mesma paleta. */
export const HAIR_COLORS = [
    '#2B2C5E',
    '#241F1F',
    '#3A2C22',
    '#5C4331',
    '#8A5A33',
    '#C4562E',
    '#E2A94E',
    '#EFE7DA',
    '#9AA3AD',
    '#6E3E8E',
    '#2E7D6E',
    '#C2455F',
];

export const CLOTHES_COLORS = [
    '#2A9D8F',
    '#3D4A7A',
    '#C2455F',
    '#E8894A',
    '#5B5B96',
    '#4F7A3D',
    '#EFE7D8',
    '#2B2440',
    '#D9788F',
    '#3FA0DE',
    '#E5B33D',
    '#8A4B2A',
];

export const BACKGROUND_COLORS = [
    '#F0AE3E',
    '#EE7A5A',
    '#6EC6BF',
    '#232A4D',
    '#F2E0A0',
    '#C9B6E8',
    '#B9E38A',
    '#E8E2D6',
    '#3FA0DE',
    '#F0C7D8',
    '#7A5C9E',
    '#2E9E96',
];

/** Usado quando o usuário nunca editou o avatar e como base da normalização. */
export const DEFAULT_AVATAR: AvatarConfig = {
    body: 'magro',
    hair: 'comprido',
    brows: 'reta',
    skin: '#EFC09A',
    hairColor: '#3A2C22',
    clothesColor: '#2A9D8F',
    background: '#F0AE3E',
};

/** Listas de ids válidos, indexadas pela chave do config. Base da normalização. */
export const SHAPE_IDS = {
    body: BODIES_OPTIONS.map((o) => o.id),
    hair: HAIRS.map((o) => o.id),
    brows: BROWS.map((o) => o.id),
} as const;
