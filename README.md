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
