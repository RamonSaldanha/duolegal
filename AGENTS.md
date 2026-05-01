# Repository Guidelines

## Project Overview
Gamified law study app ("Memorize Direito") — Laravel 12 + Vue 3 + Inertia.js, Tailwind, shadcn-vue, SQLite (dev), Stripe via Laravel Cashier.

## Architecture: What an Agent Won't Guess from File Names

### No REST API
Inertia.js renders data directly from controllers — no separate API endpoints. All data flows through `Inertia::render()` and `router.post()`/`router.get()` form submissions. The single exception is `POST /stripe/webhook`.

### Dual Game System
- **Legacy** (`/legado` prefix, `routes/legado.php`): Phase-based game (`PlayController`, `LearnController`). Where most active users are.
- **New** (root routes, `routes/web.php`): Challenges, subscriptions, disciplines, legislation editor/player (still in development).

### Route Files
| File | Purpose |
|---|---|
| `routes/web.php` | Public pages, challenges, subscriptions, ranking, disciplines, Stripe webhook |
| `routes/legado.php` | Legacy game: map, phase, review, progress, lives |
| `routes/admin.php` | Admin CRUD (behind `auth` + `admin` middleware) |
| `routes/settings.php` | User profile/password |
| `routes/auth.php` | Auth scaffolding (Breeze) |
| `routes/beta.php` | Experimental features |

### Critical: PlayController Double Calculation
The phase system is **fully stateless** — phases are recalculated from articles on every request, never stored in DB. `map()` and `findPhaseDetailsById()` both contain **identical** PASSO 1 (structure) + PASSO 2 (progress) logic. If you change one, you **must** update the other. This duplication is intentional — `findPhaseDetailsById()` recalculates from scratch for individual phase routes.

**Phase constants** (in `PlayController`):
```
ARTICLES_PER_PHASE = 6
REVIEW_PHASE_INTERVAL = 3
PHASES_PER_MODULE_PER_LAW = 6
PHASES_PER_JOURNEY = 24
```

**Progress rules:**
- ≥70% → pass, life preserved, article marked complete
- <70% → fail, life decremented (unless subscriber with infinite lives)
- XP awarded only on first pass: `difficulty_level * 5` (5–25 XP)
- Review phases require 100% on all in-scope articles to advance

### Revoked Articles
Articles containing only a header like "Art. 123." (no content/gaps) auto-complete at 100%. This handles repealed Brazilian legal articles without deleting them.

### Article Ordering
Always `CAST(article_reference AS UNSIGNED) ASC` — never sort `article_reference` alphabetically (e.g., "45-A" must follow "44").

### URL Conventions
- UUIDs in route params for security (no sequential guessing)
- Slugs for public SEO URLs (`legalReference:slug`, `article:slug`)

### Middleware Shared Data
`HandleInertiaRequests.php` injects into every Vue page via `usePage().props`: `auth.user` (id, name, email, lives, xp, is_admin, has_infinite_lives), flash messages (`success`, `error`, `info`), daily quote.

### Subscriptions (Stripe / Cashier)
- `User::subscribed('default')` → has active subscription
- `User::hasInfiniteLives()` → active subscriber = infinite lives
- `User::hasActiveSubscription()` → combined check
- Stripe webhook: `POST /stripe/webhook` — bypasses CSRF and web middleware

## Commands

```bash
# Start all services (Laravel + queue + Vite) concurrently
composer run dev

# Run tests (Pest is preferred — uses SQLite :memory: per phpunit.xml)
vendor/bin/pest
php artisan test --filter=TestName   # single test

# PHP formatting
vendor/bin/pint

# Frontend
npm run dev        # Vite HMR
npm run build      # production build
npm run lint       # ESLint with auto-fix
npm run format     # Prettier (includes organize-imports + tailwindcss plugins)

# Database
php artisan migrate
php artisan migrate:fresh --seed
```

## Coding Style
- PHP: PSR-12, 4-space indent. Run `vendor/bin/pint`.
- Vue/TS: Prettier with 4-space tabs, `printWidth: 150`, single quotes. `eslint.config.js` disables `vue/multi-word-component-names` and `@typescript-eslint/no-explicit-any`.
- Blade components in `resources/views/components/` use `kebab-case`.
- Frontend path alias: `@/` → `resources/js/`.

## Testing
- Framework: Pest. Tests run against SQLite in-memory (DB_CONNECTION=sqlite, DB_DATABASE=:memory:).
- Use `DatabaseMigrations` / `RefreshDatabase` traits.
- CI runs `./vendor/bin/pest` after building assets.

## Commit Conventions
- Conventional Commits: `feat(auth): description`, `fix(play): description`
- One logical change per PR; link issues with `Closes #123`.

## Play Debug Panel
- Component: `resources/js/pages/Play/DebugPanel.vue`
- Admin-only via `page.props.auth.user?.is_admin`
- Calculates `expandedModules` locally from props — keep `Map.vue` lean.
- "Copiar Debug Completo" button copies full JSON payload to clipboard.

## Environment
- Never commit `.env`. Keep `.env.example` up to date.
- Dev default: `DB_CONNECTION=sqlite`, `QUEUE_CONNECTION=database`, `SESSION_DRIVER=database`.
- Stripe keys in `.env`: `STRIPE_KEY`, `STRIPE_SECRET`, `STRIPE_WEBHOOK_SECRET`, plus `VITE_STRIPE_KEY`.
- Ad frequency controlled via `AD_FREQUENCY` / `VITE_AD_FREQUENCY`.
