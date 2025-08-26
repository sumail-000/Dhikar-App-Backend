# API Backend (Laravel 12)

This repository is configured as an API-only Laravel backend. Web views, Vite/Tailwind, and frontend assets have been removed.

## Requirements
- PHP >= 8.2
- Composer
- Database (configure in .env if needed)

## Setup
1. Install dependencies:
   composer install
2. Create environment file:
   cp .env.example .env
3. Generate app key:
   php artisan key:generate
4. Run migrations (optional if using DB):
   php artisan migrate
5. Start the server:
   php artisan serve

## Routes
- API routes are defined in routes/api.php and are automatically prefixed with /api.
- Example health/info endpoint:
  GET /api

Example response:
{
  "status": "ok",
  "app": "${APP_NAME}",
  "laravel": "12.x"
}

Root / returns nothing (no frontend). Use /api for endpoints.

## Testing
php artisan test

## Coding Standards
- Follow PSR-12.
- Run Pint for code style in CI or locally if added.

## Notes
- package.json has been minimized; no Node/Vite/Tailwind required.
- resources/css and resources/js are intentionally blank.
