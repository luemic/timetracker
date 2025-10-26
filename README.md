# Symfony App (project/)

This directory contains the Symfony application code (controllers, services, entities, templates) that runs inside the Docker setup at the repository root.

## Quick start (with Docker)
- Build and start containers:
  - `docker compose build`
  - `docker compose up -d`
- Open the app: http://localhost:8080

## Database & Migrations
- Connection (inside containers) is preconfigured in `.env`:
  - `DATABASE_URL="mysql://app:app@symfony-db:3306/app?serverVersion=11&charset=utf8mb4"`
- Run migrations in the PHP container:
  - `docker compose exec php sh -lc "php bin/console doctrine:migrations:migrate"`
- Validate schema:
  - `docker compose exec php sh -lc "php bin/console doctrine:schema:validate"`

## Create a user (console)
Run the interactive command inside the PHP container:
- `docker compose exec php sh -lc "php bin/console app:user:create"`

## Login & Frontend
- Default route `/` redirects to `/login`.
- After login, the main app is available at `/app`.

## Tests
- PHPUnit is installed and configured. Xdebug coverage is available in the container.
- Run unit tests:
  - `docker compose exec php sh -lc "vendor/bin/phpunit"`
  - Optionally enable coverage on demand: `docker compose exec -e XDEBUG_MODE=coverage php sh -lc "vendor/bin/phpunit"`

### End-to-end (UI) tests with Playwright
These tests run against the application in APP_ENV=test with a dedicated test database preloaded with fixtures (a default user).

1) Rebuild containers (required once after Nginx config change allowing index_test.php):
   - `docker compose build`
   - `docker compose up -d`

2) Install Composer dev dependencies (fixtures bundle) if not yet installed:
   - `docker compose exec php sh -lc "composer install"`

3) Prepare the test database (drops/creates DB, runs migrations, loads fixtures):
   - `docker compose exec php sh -lc "sh /var/www/html/scripts/test-db-reset.sh"`

4) Install Playwright in the e2e folder and browsers (on your host):
   - `cd project/tests/e2e`
   - `npm ci`
   - `npx playwright install --with-deps`

5) Run the E2E tests (against http://localhost:8080/index_test.php):
   - `npx playwright test`
   - For headed mode: `npx playwright test --headed`

Default test user (loaded by fixtures):
- E-Mail: test@example.com
- Password: test12345

## Project structure (high level)
- `src/` – PHP code (Controllers, Entities, Services, Repositories)
- `templates/` – Twig templates (includes Vue-based app in `templates/app/`)
- `migrations/` – Doctrine migrations
- `tests/` – Unit tests for services
- `public/` – Front controller and public assets

## License
This application is provided under the MIT License. See `Licence.md` in this folder.
