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

**Revoked article handling:** Articles containing only a header like "Art. 123." (no content, no gaps) auto-complete as 100%. This handles repealed Brazilian legal articles without requiring deletion.

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
