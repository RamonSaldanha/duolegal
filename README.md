O presente projeto se trata de um sistema de gamificação do aprendizado da lei.
Serve para que os estudantes de direito consigam decorar a lei.
Funciona parecido com DUOLINGO e Anki (flashcards).
O usuário vai ter que completar o texto de um artigo da lei para avançar para o próximo artigo.
Esse projeto está sendo desenvolvido no Laravel 12 com suporte do starter kit do VUE e shadcn-vue.

## Avatares

O avatar não tem imagem: é um SVG montado no navegador a partir de um JSON salvo em `users.avatar_config` (ids de peça + cores hex). O código fica em `resources/js/lib/avatar/`.

### Ver todas as peças e cores

```bash
npm run avatar:gallery
```

Gera `public/avatar-gallery.html` (fora do git). Com o Laravel rodando, abra `http://localhost:8000/avatar-gallery.html` — não precisa de login. A galeria lê o código-fonte, então rode de novo depois de mexer em qualquer peça.

### Acrescentar uma peça

1. Tipo em `resources/js/lib/avatar/types.ts`.
2. Desenho em `resources/js/lib/avatar/parts/` e a posição na ordem de camadas em `render.ts`.
3. Opção e rótulo em `resources/js/lib/avatar/options.ts` (grupo novo também entra em `DEFAULT_AVATAR`, `SHAPE_IDS` e `random.ts`).
4. O mesmo id em `config/avatar.php` — é o que o servidor aceita ao salvar.

Grupo novo (como foi a barba) também precisa de uma aba em `resources/js/pages/settings/Avatar.vue` e de uma linha na lista `shapes` de `scripts/avatar-gallery.js`.

Cores não precisam de passo 4: o servidor só exige o formato `#rrggbb`.
