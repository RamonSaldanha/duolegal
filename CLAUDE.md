# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This is a gamified law study application called "Memorize Direito" built with Laravel 12 and Vue.js. The application helps law students memorize legal articles through a Duolingo/Anki-style system where users complete missing text in law articles to progress through phases.

## Development Commands

### Frontend Development
- `npm run dev` - Start development server with Vite hot reload
- `npm run build` - Build for production
- `npm run build:ssr` - Build with SSR support
- `npm run lint` - Run ESLint with auto-fix
- `npm run format` - Format code with Prettier
- `npm run format:check` - Check code formatting

### Backend Development
- `composer run dev` - Start all services (Laravel server, queue worker, Vite) concurrently
- `php artisan serve` - Start Laravel development server
- `php artisan queue:listen --tries=1` - Start queue worker
- `php artisan migrate` - Run database migrations
- `php artisan migrate:fresh --seed` - Fresh migration with seeding

### Testing
- `php artisan test` - Run PHPUnit tests
- `vendor/bin/pest` - Run Pest tests (preferred testing framework)

### Code Quality
- `vendor/bin/pint` - Run Laravel Pint (PHP CS Fixer)

## Architecture Overview

### Tech Stack
- **Backend**: Laravel 12 with PHP 8.2+
- **Frontend**: Vue 3 with TypeScript and Inertia.js
- **UI Framework**: Tailwind CSS with shadcn-vue components
- **Database**: SQLite (development), supports MySQL/PostgreSQL
- **Queue System**: Laravel Queue for background processing
- **Payment**: Laravel Cashier (Stripe integration)
- **State Management**: Composables with Vue 3 Composition API

### Key Models & Relationships
- **User**: Has lives, XP, subscriptions, and progress tracking
- **LegalReference**: Legal documents/laws that users can study
- **LawArticle**: Individual articles within legal references with practice content
- **LawArticleOption**: Multiple choice options for fill-in-the-blank exercises
- **UserProgress**: Tracks user performance and completion status per article

### Game Mechanics
- **Lives System**: Users lose lives on incorrect answers, can be replenished
- **XP System**: Experience points earned for correct answers and completion
- **Phase System**: Articles are grouped into phases with progressive difficulty
- **Review System**: Spaced repetition with review phases every 3 regular phases
- **Subscription Model**: Freemium with Stripe payment integration

### Frontend Architecture
- **Layouts**: AppLayout (main), AuthLayout (authentication)
- **Pages**: 
  - `Play/Phase.vue` - Main game interface with fill-in-the-blank exercises
  - `Play/Map.vue` - Progress map showing available phases
  - `Dashboard.vue` - User dashboard and statistics
- **Components**: Reusable UI components in `components/ui/` following shadcn-vue patterns
- **Composables**: Shared logic like `useAppearance.ts` for theme management

### Key Features Implementation
- **Gap-filling Exercise**: Uses practice_content with `____` placeholders replaced by user selections
- **Progress Tracking**: Real-time updates with Vue reactivity and Laravel backend sync
- **Mobile Responsive**: Optimized mobile experience with collapsible text and touch-friendly interface
- **Gamification**: Confetti rewards, XP notifications, progress bars with visual feedback

### Database Structure
- Articles are ordered by `CAST(article_reference AS UNSIGNED) ASC` for proper numeric sorting
- Phase generation uses configurable constants:
  - `ARTICLES_PER_PHASE = 6` articles per regular phase
  - `REVIEW_PHASE_INTERVAL = 3` - review phase every 3 regular phases
  - `PHASES_PER_MODULE_PER_LAW = 6` for law intercalation
- UUID-based routing for security and clean URLs

### Configuration Files
- `vite.config.ts` - Vite configuration with Vue plugin and path aliases
- `tailwind.config.js` - Tailwind configuration with custom color scheme and dark mode
- `components.json` - shadcn-vue component configuration
- `tsconfig.json` - TypeScript configuration with Vue support

### Development Patterns
- Use Inertia.js for SPA-like experience without API complexity
- Prefer Composition API over Options API in Vue components
- Follow Laravel conventions for controllers, models, and routes
- Use Pest for testing over PHPUnit where possible
- Implement proper error handling with user-friendly feedback
- Use TypeScript interfaces for type safety in Vue components

### Important Constants & Configuration
- Phase generation is controlled by constants in `PlayController.php`
- Difficulty levels: 1=Iniciante, 2=Básico, 3=Intermediário, 4=Avançado, 5=Especialista
- Success threshold: 70% correct answers to pass an article
- Perfect score for reviews: 100% required to advance from review phases