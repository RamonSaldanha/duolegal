O presente projeto se trata de um sistema de gamificação do aprendizado da lei.
Serve para que os estudantes de direito consigam decorar a lei.
Funciona parecido com DUOLINGO e Anki (flashcards).
O usuário vai ter que completar o texto de um artigo da lei para avançar para o próximo artigo.
Esse projeto está sendo desenvolvido no Laravel 12 com suporte do starter kit do VUE e shadcn-vue.

## Publicar uma nova versão

No servidor, logado como o usuário do site no CloudPanel (não como root):

```bash
cd ~/htdocs/memorizedireito.com
git pull origin main
composer install --no-dev --optimize-autoloader
php artisan migrate --force
npm ci && npm run build
php artisan optimize
```

- O build precisa de **Node 20 ou mais novo** (recomendado 22, o do desenvolvimento). No CloudPanel o Node é de cada usuário do site, pelo nvm: `nvm install 22 && nvm alias default 22`.
- É `npm run build`, não `build:ssr`: o projeto não usa SSR.
- `php artisan optimize` refaz o cache de config, rotas e views. Rode a cada deploy, senão o servidor pode seguir com a config antiga (como a lista de peças de `config/avatar.php`).
- Se a versão muda a API do app (`routes/api.php`, `UserResource`), publique a web **antes** do app.

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
5. No app: `npm run avatar:sync` e uma versão nova do app (ver o README de lá).

Grupo novo (como foi a barba) também precisa de um lugar numa das abas de `EDITOR_TABS`, em `resources/js/lib/avatar/editor.ts` (vale para o editor da web e o do app), e de uma linha na lista `shapes` de `scripts/avatar-gallery.js`.

Peça exclusiva de assinante (como a toga, no grupo de fantasias): marque `premium: true` na opção em `options.ts` e ponha o id em `premium` no `config/avatar.php`. O servidor recusa salvar para quem não assina, e `User::publicAvatarConfig()` tira a peça da exibição de quem deixou de assinar — use esse método, e não o atributo `avatar_config`, em tudo que manda o avatar para a tela.

Cores não precisam de passo 4: o servidor só exige o formato `#rrggbb`.

### O app mobile

O app (`memorize-mobile`) desenha o mesmo avatar com uma **cópia** de `resources/js/lib/avatar/` em `src/lib/avatar/`. Mexa só aqui, na web, e depois atualize a cópia no app:

```bash
npm run avatar:sync
```

O desenho vai **dentro** do app: ele não baixa peças da web. Por isso peça nova só aparece no celular numa versão nova do app. Até lá, o app antigo mostra a peça padrão do grupo no lugar dela — e, se a pessoa salvar o avatar por ele, a peça nova é trocada pela padrão.

`npm run avatar:check` (no app) avisa se a cópia ficou para trás. Os keyframes da animação do editor estão copiados em `src/components/avatar/motion.ts` do app; ver `AvatarPreview.vue`.

A API do app recebe o avatar em `avatar_config` no `/me` e no ranking, e salva por `PUT /api/v1/profile/avatar`, com a mesma validação do editor da web.
