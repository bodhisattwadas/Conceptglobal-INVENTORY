# Repository Guidelines

## Project Structure & Module Organization

This is a Laravel 12 inventory application. Domain code lives in `app/`: models in `app/Models`, business operations in `app/Services`, and UI components in `app/Livewire`. Routes are split across `routes/web.php`, `routes/auth.php`, and `routes/console.php`. Blade templates, JavaScript, and CSS belong under `resources/`; Vite publishes assets to `public/build`. Migrations, factories, and seeders are under `database/`. Keep workflow tests in `tests/Feature` and isolated tests in `tests/Unit`. Do not hand-edit generated files in `build/`, `public/build/`, `vendor/`, or `node_modules/`.

## Build, Test, and Development Commands

- `composer setup` installs dependencies, prepares `.env`, generates the app key, migrates the database, and builds assets.
- `composer dev` starts the Laravel server, queue listener, application log viewer, and Vite watcher together.
- `composer test` clears cached configuration and runs the complete PHPUnit suite.
- `php artisan test --filter=RegistrationTest` runs a focused test class or method.
- `npm run dev` starts only the Vite development server; `npm run build` creates production assets.
- `vendor/bin/pint` formats PHP using Laravel Pint.

## Coding Style & Naming Conventions

Follow `.editorconfig`: UTF-8, LF endings, four-space indentation, trimmed trailing whitespace, and a final newline; YAML uses two spaces. Follow PSR-12/Laravel conventions. Use PascalCase for classes and Livewire components (`ProductForm`), camelCase for methods and variables, and snake_case for database columns. Name Blade files with kebab-case and group them by feature, for example `resources/views/vendor-invoices/show.blade.php`. Keep controllers and Livewire actions thin by placing reusable domain logic in services.

## Testing Guidelines

Tests use PHPUnit 11 and Laravel helpers with in-memory SQLite configured in `phpunit.xml`. Name files `*Test.php` and methods descriptively, such as `test_user_can_update_password`. Add Feature tests for routes, authorization, validation, Livewire interactions, and database side effects. Run `composer test` before opening a pull request; no coverage threshold is enforced.

## Commit & Pull Request Guidelines

Recent history follows Conventional Commit prefixes, primarily `feat: ...` and scoped forms such as `feat(navigation): ...`. Use an imperative, concise subject; choose an appropriate type such as `feat`, `fix`, `docs`, `refactor`, or `test`. Pull requests should explain the problem and solution, list verification commands, link relevant issues, and include screenshots for UI changes. Call out migrations, seed-data changes, or new environment variables explicitly.

## Security & Configuration

Never commit `.env`, credentials, customer data, or generated uploads. Document new settings in `.env.example`. Review migrations and seeders before running destructive commands such as `php artisan migrate:fresh --seed`.
