/**
 * Se o visitante está num iPhone/iPad. Usado para esconder o app Android de
 * quem não pode instalá-lo — ver o "Android" na landing espantava usuário de
 * iOS que poderia muito bem usar a versão web.
 *
 * Feito no navegador, e não pelo User-Agent no servidor, porque o iPadOS 13+
 * se apresenta como Mac: só o suporte a toque denuncia que é um iPad.
 */
export function isIosDevice(): boolean {
    if (typeof navigator === 'undefined') return false;

    const ua = navigator.userAgent;
    if (/iPhone|iPad|iPod/i.test(ua)) return true;

    return /Macintosh/.test(ua) && navigator.maxTouchPoints > 1;
}
