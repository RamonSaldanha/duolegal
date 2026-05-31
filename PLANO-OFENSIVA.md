# Ofensiva (streak) — Web + Mobile

Recurso de "Ofensiva" (dias consecutivos de estudo, estilo Duolingo) com calendário,
intensidade por dia, recorde e compartilhamento de imagem. Disponível na web (Laravel +
Vue/Inertia, este repositório) e no mobile (React Native/Expo em `memorize-mobile`).

## Como funciona (arquitetura)

A ofensiva e o calendário são **derivados das `XpTransaction`** — cada exercício concluído
(≥70%) já gera uma `XpTransaction` com `created_at`, nos três caminhos de jogo:

- Web (Legado): `PlayController::saveProgress()` (`source_type = play`)
- Mobile (API): `Api\V1\PlayController::submitAnswer()` (`source_type = legislation`)
- Desafios: `ChallengeController::saveProgress()` (`source_type = challenge`)

Como a fonte é a `XpTransaction`, **toda plataforma conta automaticamente** (sem ganchos por
controller) e o **histórico do usuário já aparece** (backfill grátis). O corte de "dia" usa o
fuso `America/Sao_Paulo` (o app roda em UTC); o agrupamento por dia é feito em PHP (portável p/
SQLite nos testes).

`App\Services\StreakService` (read-only) calcula tudo a partir de `xp_transactions`:
- `currentStreak(userId)` — dias consecutivos terminando hoje/ontem (janela de ~400 dias p/ HUD).
- `longestStreak(userId)` — maior sequência do histórico.
- `playedToday(userId)`, `getStats(userId, ?month)` — payload da tela (semana + calendário mensal).

`User::effectiveStreak()` / `longestStreak()` / `playedToday()` delegam ao serviço.

### Contrato (`GET /v1/streak[?month=YYYY-MM]` e props Inertia da página)

```jsonc
{
  "current_streak": 23, "longest_streak": 40, "played_today": true, "today_count": 5,
  "week": [ { "date":"2026-05-25","weekday":"Seg","count":3,"studied":true,"is_today":false,"is_future":false }, … 7 (Seg→Dom) ],
  "month": {
    "year":2026,"month":5,"label":"Maio 2026","prev":"2026-04","next":null,
    "first_weekday":4,          // offset da grade (Seg=1..Dom=7)
    "days":[ { "date":"2026-05-01","day":1,"count":0,"studied":false,"is_today":false,"is_future":false }, … ]
  }
}
```

A régua semanal é sempre a semana atual; o heatmap mensal é navegável (`prev`/`next`, `next=null` se cairia em mês futuro).

## UI (cores sólidas, espelhando a referência)

- **HUD**: chama 🔥 + ofensiva atual no header (web `AppHeader.vue`, mobile `AppHeader.tsx`); leva à tela de Ofensiva.
- **Card de resumo** (compartilhável): "N dias seguidos de estudos", subtítulo motivacional, **régua semanal** Seg–Dom (dia estudado = pílula âmbar + badge amarelo com check; futuro/sem estudo = cinza), e blocos **Sequência atual** + **Seu recorde**.
- **Calendário mensal**: heatmap de intensidade em laranja sólido (0=cinza, 1=`orange-200`, 2–3=`orange-400`, 4+=`orange-600`), com navegação de meses e legenda.
- **Compartilhar**: web via `html-to-image` + Web Share API (fallback download); mobile via `react-native-view-shot` + `expo-sharing`.

## Arquivos

**Backend:** `app/Services/StreakService.php` · `app/Models/User.php` · `app/Http/Controllers/Api/V1/StreakController.php` · `app/Http/Controllers/StreakController.php` (web) · `app/Http/Controllers/Api/V1/PlayController.php` (userPayload) · `app/Http/Resources/Api/UserResource.php` · `app/Http/Middleware/HandleInertiaRequests.php` · `routes/api.php` (`/v1/streak`) · `routes/web.php` (`/ofensiva`) · `tests/Feature/StreakTest.php`

**Web:** `resources/js/pages/Ofensiva/Index.vue` · `resources/js/components/AppHeader.vue` · dep `html-to-image`

**Mobile (`memorize-mobile`):** `src/api/streak.ts` · `src/screens/OfensivaScreen.tsx` · `src/components/layout/AppHeader.tsx` · `src/navigation/AppTabs.tsx` (PlayStack) · `src/stores/userStore.ts` · `src/theme/colors.ts` (escala `orange`) · deps `react-native-view-shot`, `expo-sharing`

## Verificação

- Testes: `php -d extension=pdo_sqlite -d extension=sqlite3 vendor/bin/pest --filter=Streak` (8 passam, 48 asserts).
- Mobile: `npx tsc --noEmit` limpo. Web: `vue-tsc` sem erros nos arquivos da ofensiva.
- **Aplicar a migration de XP** caso ainda não esteja no banco: `php artisan migrate`. (As colunas de streak foram descartadas — não há nova migration própria da ofensiva.)
- Manual: concluir um exercício na web e no mobile → a chama do header incrementa e o dia aparece marcado em `/ofensiva`; navegar entre meses; "Compartilhar" gera a imagem.
