# lab_ordering

Lab-test ordering platform: Laravel 12, React 19 + TypeScript via Inertia.js 2, Tailwind CSS 4, MySQL 8 (PostgreSQL-compatible).

Project docs: [`docs/ai/`](docs/ai) (PROJECT, CONVENTIONS, DESIGN).

## Requirements

PHP 8.2+, Composer, Node 20+, MySQL 8 (or SQLite for quick local use).

## Setup

```bash
composer install
npm install
cp .env.example .env          # set DB_* for your MySQL database
php artisan key:generate
php artisan migrate --seed
composer run dev              # server, queue, logs and Vite at http://localhost:8000
```

Seeded logins (password `password`): `test@example.com` (customer) and `admin@example.com` (admin).
To make an existing user an admin: `php artisan user:role someone@example.com admin`.

## Checks

```bash
./vendor/bin/pint --test
php artisan test
npm run types && npm run lint && npm run format:check
npm run build
```

CI (`.github/workflows/ci.yml`) runs these on every PR and on pushes to `main`, with the backend tested against both MySQL 8 and PostgreSQL 16.
