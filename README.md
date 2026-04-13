# Internal Academy

Laravel + Inertia (Vue) app for browsing workshops, managing sign-ups, and giving admins tools to run sessions.

## Features

- **Authentication** — Login, registration, email verification, and profile (Breeze-style flow).
- **Roles** — Users have a `role` (`admin` or `user`). Admins use middleware-backed routes; policies gate workshop actions.
- **Public workshops** — List upcoming and past workshops with capacity and live spot counts (Reverb + Echo).
- **Registration** — Signed-in users can register or cancel for upcoming workshops, with capacity checks and overlap rules so one person is not double-booked for concurrent sessions.
- **FIFO waitlist** — When a workshop is full, users can join a waiting list; if someone cancels, the next eligible person is promoted to an active registration. `PromotedFromWorkshopWaitlist` is reserved for future toast/email. Dashboard shows registered and waitlisted upcoming workshops with live counts where Echo is configured.
- **Admin workshop CRUD** — Admins create, edit, view, and delete workshops (slug, schedule, duration, capacity, description).
- **Admin statistics** — On the admin workshop index, a statistics panel shows total active registrations and the most popular workshop (by count, ties by lowest id), updated in real time over a private broadcast channel when registrations or workshop records change.
- **Reminders** — `php artisan academy:remind` emails participants whose workshops are **tomorrow** (app timezone), one message per user listing their sessions; `--dry-run` lists recipients without sending. Configure mail (e.g. Resend + `MAIL_MAILER=resend` and `RESEND_API_KEY`) in `.env`.

## Stack (high level)

PHP / Laravel, Inertia + Vue 3, Tailwind, SQLite by default, PHPUnit tests for auth, workshops, registrations, waitlist, reminders, and admin flows.

## Install

1. **PHP 8.3+**, **Composer**, and **Node.js** (with npm) available on your machine.
2. Clone the repo and enter the project directory.
3. Install PHP dependencies: `composer install`
4. Environment: `cp .env.example .env` then `php artisan key:generate`
5. **SQLite (default):** ensure `DB_CONNECTION=sqlite` in `.env` and that the database file exists, e.g. `touch database/database.sqlite`
6. Run migrations: `php artisan migrate`
7. Install front-end deps and build assets: `npm install` then `npm run build` (for local development with hot reload, use `npm run dev` alongside `php artisan serve` — or run `composer run dev` to start server, Vite, logs, and Reverb together).

Optional one-shot setup (after `.env` exists): `composer run setup`

After seeding (below), you can sign in as **admin@example.com** or **test@example.com** with password **password** (see `DatabaseSeeder` and `UserFactory`).


I suggest to run the app with `composer run dev` command.

## Run tests

```bash
composer test
```

Or directly: `php artisan test` (add `--compact` for shorter output). To run a single file: `php artisan test tests/Feature/ExampleTest.php`

## Seeders

- **Run all seeders** (uses `DatabaseSeeder`, which calls `UserSeeder`, `WorkshopSeeder`, etc.):  
  `php artisan db:seed`

- **Fresh database + seed** (SQLite: resets the DB file first when using the project’s helper):  
  `composer run migrate:fresh-db`  
  or: `php artisan migrate:fresh --seed`

- **Create a new seeder class** (empty class you then edit and register in `DatabaseSeeder` if needed):  
  `php artisan make:seeder YourSeederName`
