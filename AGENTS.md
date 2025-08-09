# Repository Guidelines

## Project Structure & Module Organization
- `app/`: Lógica de domínio e aplicação (`Http/Controllers`, `Models`, `Policies`, `Jobs`, `Events`).
- `bootstrap/`, `config/`: Boot e configurações (cacheáveis via Artisan).
- `database/`: `migrations/`, `seeders/`, `factories/` para schema e dados de teste.
- `public/`: ponto de entrada HTTP (`index.php`) e assets publicados.
- `resources/`: `views/` (Blade), `js/` e `css/` (Vite).
- `routes/`: `web.php`, `api.php`, `console.php`, `channels.php`.
- `storage/`: logs, cache, uploads; `tests/`: `Feature/` e `Unit/`.
Ex.: controlador `app/Http/Controllers/UserController.php` + rota em `routes/web.php` + view em `resources/views/users/index.blade.php`.

## Build, Test, and Development Commands
- Instalar: `composer install` e `cp .env.example .env && php artisan key:generate`.
- Servir local: `php artisan serve` (API/web) e `npm ci && npm run dev` (Vite).
- Banco: `php artisan migrate --seed` (ou `migrate:fresh --seed` para resetar).
- Otimizações: `php artisan optimize`, `config:cache`, `route:cache`.
- REPL: `php artisan tinker`.

## Coding Style & Naming Conventions
- Padrão: PSR‑12, indentação 4 espaços; use Laravel Pint: `vendor/bin/pint`.
- Qualidade: PHPStan (Larastan): `vendor/bin/phpstan analyse` (nível conforme `phpstan.neon`).
- Nomes: Models `StudlyCase` (e.g., `User`), controladores `FooController`, Form Requests `StoreUserRequest`, colunas DB `snake_case`.
- Blade: componentes em `resources/views/components/` com `kebab-case` (ex.: `<x-alert />`).

## Testing Guidelines
- Framework: Pest (padrão) ou PHPUnit. Rodar: `php artisan test` ou `vendor/bin/pest`.
- Estrutura: espelhar feature em `tests/Feature` e unidades em `tests/Unit`.
- Dados: use `factories` e `DatabaseMigrations`/`RefreshDatabase`.
- Cobertura: alvo ≥80% para código alterado (`vendor/bin/phpunit --coverage-text`).

## Commit & Pull Request Guidelines
- Commits: Conventional Commits (ex.: `feat(auth): implementa fluxo de login`).
- PR: uma mudança lógica por PR; descreva objetivo, passos de teste, migrações e variáveis `.env` novas; linke issues (`Closes #123`).
- Checks: CI deve passar (build, lint, testes); atualize docs quando houver mudança de comportamento.

## Security & Configuration Tips
- Nunca versione `.env`; mantenha `.env.example` atualizado. `APP_DEBUG=false` em produção.
- Após deploy: `php artisan config:cache` e `route:cache`.
- Valide uploads, sanitize entrada e cuide das permissões de `storage/` e `bootstrap/cache`.
