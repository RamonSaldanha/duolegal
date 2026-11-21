# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Gamified law study application ("Memorize Direito") built with Laravel 12 and Vue 3. Law students memorize legal articles through Duolingo/Anki-style fill-in-the-blank exercises, progressing through phases with lives and XP.

## Development Commands

```bash
composer run dev          # Start all services (Laravel + queue + Vite) concurrently
php artisan migrate       # Run database migrations
php artisan migrate:fresh --seed  # Fresh migration with seeding
vendor/bin/pest           # Run Pest tests (preferred over php artisan test)
php artisan test --filter=testName  # Run specific test
vendor/bin/pint           # Run Laravel Pint (PHP CS Fixer)
npm run dev               # Start Vite dev server (HMR)
npm run build             # Build for production
npm run lint              # ESLint with auto-fix
npm run format            # Prettier formatting
```

## Architecture Overview

**Tech Stack:** Laravel 12 / PHP 8.2+, Vue 3 + TypeScript + Inertia.js, Tailwind CSS + shadcn-vue, SQLite (dev), Laravel Cashier (Stripe), Laravel Queue.

**No API pattern:** Inertia.js renders data directly from controllers — no separate REST API endpoints. All data flows through `Inertia::render()` responses and `router.post()` form submissions.

### Dual System Architecture

There are two coexisting game systems:

- **Legacy system** (`/legado` prefix, `routes/legado.php`): Original phase-based game using `PlayController` and `LearnController`. Most active users are here.
- **New system** (root routes, `routes/web.php`): Challenges, subscriptions, disciplines, and newer legislation editor/player features.

### Route Files

| File                  | Purpose                                                       |
| --------------------- | ------------------------------------------------------------- |
| `routes/web.php`      | Public pages, challenges, subscriptions, ranking, disciplines |
| `routes/legado.php`   | Legacy game: map, phase, review, progress, lives              |
| `routes/admin.php`    | Content management (behind AdminMiddleware)                   |
| `routes/settings.php` | User profile and password settings                            |
| `routes/auth.php`     | Authentication scaffolding (Breeze)                           |
| `routes/beta.php`     | Experimental features                                         |

### Core Game Logic — `PlayController.php`

This is the most complex file. The phase system is **fully stateless** — phases are recalculated from articles and constants on every request, never stored in the database.

**Phase generation constants:**

```php
const ARTICLES_PER_PHASE = 6;         // Articles per regular phase
const REVIEW_PHASE_INTERVAL = 3;      // Insert review phase every N regular phases
const PHASES_PER_MODULE_PER_LAW = 6;  // Max regular phases per law per module (intercalation control)
const PHASES_PER_JOURNEY = 24;        // UI pagination — phases per journey
```

**Critical pattern — double calculation:** The `map()` method and `findPhaseDetailsById()` both contain identical PASSO 1 (structure building) + PASSO 2 (progress calculation) logic. If you change phase generation in one place, you **must** update both. This duplication is intentional — `findPhaseDetailsById()` recalculates from scratch for individual phase/review routes without needing the full map context.

**Phase structure algorithm:**

1. **PASSO 1:** Build phase structure with law intercalation. Phases distribute across modules so users alternate between multiple laws rather than completing one law fully before starting another.
2. **PASSO 2:** Walk phases sequentially: calculate progress per phase, find the first incomplete unblocked phase (marked `is_current`), block all phases after it. Regular phases require all articles attempted; review phases require all in-scope articles ≥ 100%.

**Revoked article handling:** Articles containing only a header like "Art. 123." (no content, no gaps) auto-complete as 100%. This handles repealed Brazilian legal articles without requiring deletion. In the beta trail (`/beta/play`) the same idea applies to blocks: `LegislationSegment::historicalLabel()` detects a block left with only its label ("IX-A -;", "Art. 194."), because the import strips the "(VETADO)"/"(Revogado)" text. The beta still plays it as a normal challenge (it must be verified to finish the phase), but gives no XP for it and shows a note explaining it was vetoed or revoked. The legacy map and the app API are unchanged.

**Progress rules:**

- Pass threshold: ≥ 70% correct → `UserProgress.is_completed = true`, life preserved
- Fail: < 70% → decrement life (unless subscriber with infinite lives)
- XP awarded only on first pass (not retakes): `difficulty_level * 5` = 5–25 XP
- Review phases require 100% on all in-scope articles to advance

### Key Models

**User:** `hasLives()`, `hasInfiniteLives()` (active subscriber), `decrementLife()`, `incrementLife()` (max 5). XP stored as immutable `xp_transactions` records; the `xp` attribute sums them. `addXp($amount, $sourceType, $sourceId)` creates transactions.

**LawArticle:** `practice_content` uses `_____` placeholders for fill-in gaps. `article_reference` is a string (e.g., "45-A") always queried with `CAST(article_reference AS UNSIGNED) ASC` for proper numeric ordering — never sort articles alphabetically.

**UserProgress:** `updateProgress()` static method increments `attempts`, tracks `wrong_answers`, sets `is_completed` at ≥70%, increments `revisions` on subsequent passes of already-completed articles.

**Challenge:** `selected_articles` is a JSON array of article IDs. Participants tracked via pivot table with per-article scores. Separate from global `UserProgress` records.

**Legislation / LegislationSegment:** Newer model for full legislation text with "lacunas" (gaps). Editor and player components exist in `components/legislation-editor/` and `components/legislation-play/` but system is still in development.

### Conquistas: estrelas por disciplina

`DisciplineStarService` calcula de 0 a 5 estrelas a partir do XP em cada disciplina. O "Nível Global" continua em `XpService::calculateLevel`, sem limite.

- **Metas:** ficam fixas em `disciplines.star_thresholds` (`72,52 × √XP potencial`; 2, 3 e 4 estrelas valem 3%, 15% e 45% dessa meta). Cadastrar uma lei não as altera.
- **Estrela não se perde:** `user_discipline_stars` guarda o maior valor conquistado, e a tela mostra sempre o maior.
- **XP antigo:** a origem `migration` foi distribuída por área em `discipline_xp_credits`. O ranking e o XP do usuário não mudam.
- **App já instalado:** a API mantém `level`, `current_xp_in_level`, `xp_for_next_level` e `progress_percent`, porque builds antigas desenham o badge antigo com eles. Os campos novos (`stars`, `next_star_xp`, `badge_url`) são só acréscimos; não remova nem mude o formato dos antigos.
- **Conversão:** `php artisan disciplines:convert-to-stars` simula; `--force` grava; `--recalculate` recalcula metas fixadas sem tirar estrelas.
- **Artes:** `public/img/badges/{badge_key}-{estrelas}.png` (0 = bloqueado) são as originais. A URL leva `?v=` (`DisciplineStarService::ORIGINAL_BADGES_VERSION`); suba o número ao redesenhá-las, senão o app e o navegador mostram a imagem antiga do cache. Disciplina sem arte usa o `DisciplineBadge` antigo.
- **Estilo chapado:** desde 20/11/2026 a arte é chapada (sem sombra, brilho nem degradê) e usa a paleta do editor (`SHIELD_SWATCHES`). Badge gerado no admin antes disso (`Discipline::BADGE_STYLE_SINCE`) aparece na lista de disciplinas com o botão "Redesenhar no estilo novo", que desenha de novo no navegador e envia para `PUT /admin/disciplines/{uuid}/badge`.
- **Criar ou editar badge:** o formulário do admin (`StarBadgeEditor`) desenha os 6 PNGs no navegador com `resources/js/lib/badges/art.ts` e os envia. Eles ficam em `storage/app/private/discipline-badges/{uuid}`, são servidos por `/discipline-badges/{uuid}/{estrelas}` e têm prioridade sobre as originais. Ilustração nova exige mudar `art.ts` e `BADGE_ILLUSTRATIONS` em `Admin\DisciplineController`.

### Guia de componentes

`docs/design/componentes.html` é onde se decide como cada componente do site e do app deve ser (por enquanto botões e avisos). Cada componente tem a proposta em discussão e o inventário do que existe hoje, com o CSS real. Abra com a configuração `docs-design` do `.claude/launch.json` (`php -S 127.0.0.1:8090 -t docs/design`, depois `/componentes.html`). O arquivo também é publicado como o artefato "Componentes do Memorize" no claude.ai (https://claude.ai/artifact/EetaJVoZ2ySAeUGaoCz7uP): ao editá-lo, publique de novo nesse link (passe a URL como `url`) a versão sem `<!doctype>`, `<html>`, `<head>` e `<body>`.

- Enquanto a proposta não for aplicada, não crie botões novos fora do `GameButton` nem avisos com desenho próprio: siga a barra de resultado (`ResultBar.vue`).
- Só o que se aperta tem 3D (base embaixo e afundar ao toque). Aviso, cartão de informação e painel ficam chapados.
- Animação em botão não usa `transform`, porque apaga o afundar. Use `scale` ou `translate`.

### Perfil público

Cada linha do ranking abre o perfil: na web, `/perfil/{uuid}` (`PublicProfileController`); no app, `GET /api/v1/profiles/{uuid}`, que usa o mesmo `payload()`. O ranking da API traz o `uuid` de cada linha. As Configurações do app usam `GET /api/v1/settings`, `PUT /api/v1/settings/achievements` e `PUT /api/v1/settings/emails`; a descrição é salva por `PATCH /api/v1/profile` (`bio`).

- **Campos:** o controller escolhe um a um o que sai (primeiro e último nome, `publicAvatarConfig()`, `bio`, ofensiva atual e as conquistas com estrela, sem XP). Não mande o model inteiro para a tela.
- **Conquistas exibidas:** `users.hidden_achievements` guarda as disciplinas que a pessoa escondeu em Configurações, então uma conquista nova aparece sozinha. As escondidas não saem do servidor.
- **`uuid`, `bio` e `hidden_achievements`:** ficam em `$hidden` para não vazar em `GET /api/user` e no criador dos desafios. A URL usa `uuid` para ninguém listar os usuários percorrendo ids; não mude o `getRouteKeyName` do `User`, porque o admin usa o id.
- **Descrição:** até 160 caracteres, editada em Configurações e exibida como texto puro. O admin apaga na ficha do usuário.

### Material de estudo

Resumo em markdown + questões no estilo de banca, escritos por um agente de IA (Claude Code, Codex, OpenCode) na máquina do admin e gravados por rotas REST. O site não gera conteúdo. No mapa beta vira o desafio do quadrado (o último passo dele) e, ao fim de cada etapa, o Dia da prova.

- **Um material por quadrado:** cada linha de `study_materials` é um intervalo contínuo de blocos de uma lei (`first_block_id` a `last_block_id`), sempre do primeiro bloco pendente até o fim do quadrado do mapa beta (40 blocos; `StudyMaterialService::nextRange`). O agente não escolhe o corte: `/next` devolve o quadrado e o site recusa outro intervalo. `block_ids` são os blocos que o material cobre; o agente pode deixar alguns de fora ou pular o quadrado inteiro (`skipped`, com `skip_reason`). Até 6 questões. A posição é medida por `char_start`. As regras ficam em `StudyMaterialService` e valem igual para a API e para a edição no admin.
- **Por que o quadrado é fixo:** as fases são cortadas de 10 em 10 blocos desde o início da lei e a troca de lei acontece em múltiplos de 16 fases, então o quadrado N de uma lei é o mesmo para todo usuário. Inserir ou remover um bloco no meio da lei desloca os quadrados seguintes; o material continua no quadrado em que o último bloco dele cair.
- **Rascunho, publicado, pulado:** o agente grava como `published` (manda `"status": "draft"` só se o usuário pedir revisão antes); só `published` aparece no mapa, e o admin pode despublicar. Apagar um material ou um trecho pulado devolve os blocos a pendente.
- **Hash:** `content_hash` é o sha256 dos textos dos blocos cobertos. Se o texto muda ou um bloco some, o material fica "desatualizado": sai do mapa e não publica até o admin usar "Revisei e confirmo" ou apagar.
- **Rotas do agente:** `/api/v1/admin/study-materials` (`routes/api.php`), com `auth:sanctum` e o middleware `study-materials.agent`, que exige admin e a habilidade `study-materials` conferida na lista literal do token. Não troque por `tokenCan()`: o token do app (`mobile`) tem `*` e passaria. Os erros dessas rotas saem sempre em JSON (`bootstrap/app.php`) e são escritos para o agente se corrigir.
- **Desafio do quadrado:** o quadrado que tem material publicado ganha um passo a mais (5 em vez de 4), feito em `BetaCheckpointController` (`/beta/play/desafio/{uuid do último bloco do quadrado}`). Abre com as fases do quadrado feitas e não gasta vida. Ele trava só a passagem para o quadrado seguinte: se a próxima fase a jogar é a primeira de um quadrado e o desafio do anterior está por fazer, o passo atual é o desafio (`BetaPlayController::trail`). Quem já passou adiante (material publicado depois) não é puxado de volta: o desafio fica aberto no quadrado concluído, com uma marca roxa. Quadrado sem material tem só as 4 fases. Material antigo que termina no mesmo quadrado de outro entra no mesmo desafio.
- **Progresso do desafio:** cada passo é gravado na hora: `summary` guarda o resumo lido e `answer` guarda cada resposta (vale a primeira dada a cada questão). Sair no meio não perde nada. Fica em `user_beta_checkpoints`, uma linha por quadrado (o último bloco dele): `done_material_ids` e `in_progress`. Material já concluído é revisão: só corrige, não grava nem dá XP.
- **XP do desafio:** `10 + 15 por acerto` (constantes em `BetaCheckpointController`), dado quando o material é concluído e só na primeira conclusão, origem `study_material` com o `legislation_id`.
- **Dia da prova:** fase paralela ao lado do último quadrado de cada etapa (`BetaExamController`, `/beta/play/prova/{uuid do último bloco da etapa}`). Prova com tempo, só com as questões dos desafios dos quadrados da etapa, em ordem sorteada, sem resumo. Estados: `locked` (faltam fases ou desafios da etapa), `empty` (sem questões), `available`, `complete` (já entregou uma prova). Fica fora do avanço da trilha e não gasta vida.
- **Tempo da prova:** 15 s por questão de certo/errado e 35 s por múltipla escolha, somados (constantes `EXAM_*` em `BetaPlayController`). Quem manda é o servidor: `user_beta_exams.deadline_at`; resposta fora do prazo (com 3 s de folga) não entra e a prova é entregue. O relógio da tela só mostra. Prova esquecida aberta é entregue quando a pessoa volta.
- **XP da prova:** soma dos acertos, 15 por acerto, origem `beta_exam`. A tela mostra o XP somado ao lado do relógio, mas ele só é creditado na entrega (última resposta, fim do tempo ou "Entregar a prova"). Errar não tira nada. Cada questão dá XP uma vez só, no primeiro acerto (`rewarded_question_ids`), então refazer rende só o que ainda não foi acertado.
- **Sem regra de assinante:** o desafio do quadrado e o Dia da prova são de qualquer usuário. O que será exclusivo do Premium ainda vai ser definido.
- **Gabarito:** nunca vai para o navegador antes da resposta; a correção é no servidor. Na prova, o comentário de cada questão só chega no resultado.
- **Sons:** `resources/js/lib/sounds.ts` gera os sons com a Web Audio API. `ResultBar` toca o de acerto sempre que aparece como acerto; o erro é silencioso.
- **Resumo:** guardado em markdown puro e convertido por `StudyMaterialService::renderSummary` (`Str::markdown`, HTML embutido removido, links inseguros bloqueados). A tela só usa `v-html` com esse HTML, pelo componente `StudySummary`.
- **Skill:** `.claude/skills/gerar-material-estudo/SKILL.md`, com cópia idêntica em `.agents/skills/` (um teste falha se divergirem). A página `/admin/study-materials/instalar` gera o token, monta o `.zip` a partir dessa pasta e traz os comandos por ferramenta. Mudou uma rota ou regra de validação: atualize a skill e o número de versão dela.

### Mapa novo no app (API `/trail`)

No app novo (`memorize-mobile`), o mapa beta é o único mapa, jogado pelas rotas `/api/v1/trail` (`routes/api.php`): mapa, fase, `fail`, `finish`, desafio do quadrado e Dia da prova.

- **Mesma regra da web:** `Api\V1\TrailController`, `TrailChallengeController` e `TrailExamController` herdam os controllers do beta. Os dados saem de `mapPayload`, `phasePayload`, `checkpointPayload` e `examPayload`, que a web também usa; as rotas JSON (`betaFail`, `betaFinish`, `summary`, `answer`, `start`, `finish`) são as mesmas. Mudou a regra no beta, vale para o app.
- **Quem vê:** pela API, todo aluno logado (quem instalou o app novo já está no mapa novo). Na web, `/beta/play` segue só para administradores (middleware `admin`) e o `/play` continua sendo o mapa dos alunos.
- **Recusas em JSON:** onde a web redireciona, a API responde `403 no_lives` ou `409 unavailable`.
- **Resumo:** o desafio da API manda também `summary_blocks` (`StudyMaterialService::summaryBlocks`), o resumo em blocos tirado da mesma árvore do CommonMark, para o app desenhar e fazer a leitura guiada.
- **App antigo:** as rotas `/api/v1/play/*` continuam; quem tem build antiga segue no mapa atual.

### Shared Data via Middleware

`HandleInertiaRequests.php` injects into every Vue page via `usePage().props`:

- User object: `id`, `name`, `email`, `lives`, `xp`, `is_admin`, `has_infinite_lives`
- Flash messages: `success`, `error`, `info`
- Daily quote

### Frontend Pages

| Page        | Path                         | Purpose                                                                       |
| ----------- | ---------------------------- | ----------------------------------------------------------------------------- |
| Map         | `Legado/Play/Map.vue`        | Phase list with journey pagination, progress display, auto-scroll to current  |
| Phase       | `Legado/Play/Phase.vue`      | Fill-in-the-blank game loop with XP/life notifications                        |
| DebugPanel  | `Legado/Play/DebugPanel.vue` | Admin tool showing phase structure and progress details                       |
| Challenges  | `Challenges/`                | Create, browse, join custom article challenges                                |
| Disciplines | `Disciplines/Index.vue`      | Completion % by legal topic                                                   |

### Database Conventions

- Always filter with `.where('is_active', true)` on articles and legal references
- Always order articles with `CAST(article_reference AS UNSIGNED) ASC`
- UUIDs used in routes for security (no sequential guessing); slugs used for public SEO URLs
- Stripe subscription data managed entirely by Laravel Cashier; check `User::subscribed('default')` for active subscription
