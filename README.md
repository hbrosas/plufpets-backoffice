# Employee Portal

A blank Laravel and Vue application. Sign-in uses an employee number and PIN code. There is no account registration yet.

## Sign in

These credentials are hard-coded in `config/employee.php`:

| Field | Value |
| --- | --- |
| Employee number | `10001` |
| PIN code | `1234` |

## Local setup

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
npm run build
php artisan serve
```

Open `http://localhost:8000` for the public site, and `http://localhost:8000/backoffice` to sign in. For frontend hot reload, run `npm run dev` in another terminal.

Login does not need a database. Sessions are stored in an encrypted cookie so the same sign-in works on Vercel before Supabase is connected.

## Supabase

The app uses Supabase as Postgres. Locally it stays on SQLite until you fill in the connection. In the Supabase dashboard, copy the **transaction pooler** settings (port `6543`) and set:

```
DB_CONNECTION=pgsql
DB_HOST=aws-0-<region>.pooler.supabase.com
DB_PORT=6543
DB_DATABASE=postgres
DB_USERNAME=postgres.<project-ref>
DB_PASSWORD=<database-password>
DB_SSLMODE=require
SUPABASE_URL=https://<project-ref>.supabase.co
SUPABASE_ANON_KEY=
SUPABASE_SERVICE_ROLE_KEY=
```

Then run `php artisan migrate`.

## Vercel

`vercel.json` runs the app with the `vercel-php@0.9.0` runtime (PHP 8.5) and builds the Vue assets. In the Vercel project settings, set:

- `APP_KEY` — generate one with `php artisan key:generate --show`
- `APP_URL` — the deployment URL
- The Supabase variables above, when the database should be used

`SESSION_DRIVER=cookie` is set for Vercel because the filesystem is read-only outside `/tmp`. Laravel cache and compiled views also use `/tmp`.
