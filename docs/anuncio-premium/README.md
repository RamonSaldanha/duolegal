# Anúncio do Premium (vídeo)

O vídeo do nosso anúncio na tela sem energia, `public/ads/memorize-premium.mp4` (24,6 s, 1080x1920, 30 fps).

- `ad.html`: as cenas. `window.seek(t)` desenha o quadro do segundo `t`, sem animação solta, então cada quadro sai igual sempre.
- `avatars.ts` / `avatars.json`: os avatares, desenhados com o mesmo código do site (`resources/js/lib/avatar`).
  Para refazer: `npx esbuild docs/anuncio-premium/avatars.ts --bundle --platform=node --format=esm | node --input-type=module > docs/anuncio-premium/avatars.json`.
- `a/`: logo, selos e os quadros dos GIFs (`sem-energia`, `premium-nomeado`).
- `render.mjs`: abre o `ad.html` no Chromium do Playwright e manda os quadros ao ffmpeg (`node render.mjs stills 1,4.5,8` tira fotos; `node render.mjs video` gera `video-noaudio.mp4`). Precisa de `playwright-core` instalado e do Chromium em `%LOCALAPPDATA%/ms-playwright`.
- `audio.py`: a trilha (batida sintetizada e os sons do app nos momentos da animação), em `track.wav`. Ajuste o caminho `OUT` e `APP`.
- Juntar: `ffmpeg -i video-noaudio.mp4 -i track.wav -c:v copy -c:a aac -b:a 192k -shortest -movflags +faststart public/ads/memorize-premium.mp4`.
