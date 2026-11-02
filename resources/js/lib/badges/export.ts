import { badgeSVG } from './art';
import { FONT_B64 } from './font';

export interface BadgeConfig {
    icon: string;
    text: string;
    shield: string;
    ribbon: string;
}

const IMAGE_SIZE = 512;
const MAX_STARS = 5;

let fontLoading: Promise<void> | null = null;

/** Sem a fonte carregada, o texto da faixa é medido e desenhado errado na prévia. */
export function loadBadgeFont(): Promise<void> {
    fontLoading ??= new FontFace('Fredoka', `url(data:font/woff2;base64,${FONT_B64}) format("woff2")`, { weight: '600' }).load().then((font) => {
        document.fonts.add(font);
    });

    return fontLoading;
}

function drawSvg(svg: string): Promise<HTMLCanvasElement> {
    const url = URL.createObjectURL(new Blob([svg], { type: 'image/svg+xml' }));

    return new Promise<HTMLCanvasElement>((resolve, reject) => {
        const image = new Image();
        image.onload = () => {
            const canvas = document.createElement('canvas');
            canvas.width = canvas.height = IMAGE_SIZE;
            canvas.getContext('2d')!.drawImage(image, 0, 0, IMAGE_SIZE, IMAGE_SIZE);
            resolve(canvas);
        };
        image.onerror = () => reject(new Error('Não foi possível desenhar o badge.'));
        image.src = url;
    }).finally(() => URL.revokeObjectURL(url));
}

function toGrayscale(canvas: HTMLCanvasElement): void {
    const context = canvas.getContext('2d')!;
    const image = context.getImageData(0, 0, canvas.width, canvas.height);
    const pixels = image.data;

    for (let i = 0; i < pixels.length; i += 4) {
        const gray = 0.2126 * pixels[i] + 0.7152 * pixels[i + 1] + 0.0722 * pixels[i + 2];
        pixels[i] = pixels[i + 1] = pixels[i + 2] = gray;
    }

    context.putImageData(image, 0, 0);
}

/** Gera um PNG para cada quantidade de estrelas: índice 0 é o badge bloqueado, em cinza. */
export async function exportBadgeImages(config: BadgeConfig): Promise<File[]> {
    await loadBadgeFont();

    const files: File[] = [];
    for (let stars = 0; stars <= MAX_STARS; stars++) {
        const canvas = await drawSvg(badgeSVG({ ...config, stars }, `x${stars}`, true));
        if (stars === 0) {
            toGrayscale(canvas);
        }

        const blob = await new Promise<Blob | null>((resolve) => canvas.toBlob(resolve, 'image/png'));
        if (!blob) {
            throw new Error('Não foi possível gerar a imagem do badge.');
        }

        files.push(new File([blob], `${stars}.png`, { type: 'image/png' }));
    }

    return files;
}
