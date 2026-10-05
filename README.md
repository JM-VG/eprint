# E-Print

Online print request and sales system for the MinSU printing room. It serves two locations, the **Main IT Printing Room** and the **CCS Office**. Students upload print jobs from anywhere and pick them up at the counter. Staff clock in at a location, work the queue, collect cash or GCash at pickup and record walk-in sales. Every staff member sees their own commission. One central database replaces the old per-PC system.

## Features

**Students**
- Sign up with Student ID (checked against a configurable pattern, e.g. `MMC2024-00123`); an admin approves the account.
- Submit document jobs (PDF, DOCX, JPG, PNG, up to 20 MB) or photo jobs (1x1, 2x2, passport) with a live price estimate.
- Track jobs, show the claim code and QR at the counter, edit or delete a job while it is pending.
- Get a notification when a job is ready for pickup.

**Staff**
- Clock in at a location; students see which printing rooms are open (banner refreshed every 15 s).
- Work the queue per location: accept (final page count and total), reject with a reason, start printing, mark ready.
- Claim at pickup with cash or GCash (unique reference number). The sale and commission are recorded together in one atomic write.
- Record walk-in sales with line items and sheets used; submit own jobs.
- Commission dashboard: today, this week, this month, all time, unpaid balance, own sales list.

**Admins** (everything staff can do, plus)
- Approve, reject or disable accounts; manage staff; reset passwords to a temporary one.
- Price list and settings (commission rate, GCash details and QR, file retention, Student ID pattern).
- Sales report with CSV export, commission payouts, shift history, audit log, announcements.

## Stack

Laravel 12, PHP 8.2+, Firebase Realtime Database, Blade + Breeze (Tailwind), Pest, Pint.
Packages: `smalot/pdfparser` (PDF page count) and `simplesoftwareio/simple-qrcode` (claim QR). The front end uses plain JavaScript `fetch` polling, with no WebSockets.

## Local setup

Requirements: PHP 8.2+ with `openssl`, `curl`, `fileinfo`, `mbstring`, `zip`, `gd` and `bcmath`, plus Composer and Node 18+. Laragon works out of the box. There is no SQL database: data lives in the Firebase Realtime Database.

```bash
composer install
npm install && npm run build
cp .env.example .env
php artisan key:generate
# save the Firebase service account key as storage/app/firebase/service-account.json
# set ADMIN_USERNAME / ADMIN_PASSWORD in .env
php artisan firebase:ping --write
php artisan db:seed
php artisan storage:link
php artisan serve
```

There are no migrations any more: `php artisan migrate --seed` from older versions is replaced by `php artisan db:seed` (or `php artisan firebase:seed --fresh` to wipe the Realtime Database first).

Seeding creates the two locations, the admin account from `ADMIN_USERNAME`/`ADMIN_PASSWORD`, a sample price list (all prices 0; set real prices under **Price List**) and default settings. With `APP_ENV=local` it also creates demo accounts, all with the password `password`: staff `staff1` and `staff2`, and students `MMC2024-00001` and `MMC2024-00002` (approved) and `MMC2024-00003` (pending).

Run the scheduler locally with `php artisan schedule:work`.

## Data storage

All data is in the Firebase Realtime Database, set up in `config/firebase.php` (`FIREBASE_DATABASE_URL`, `FIREBASE_CREDENTIALS`). The server signs in with the service account; the browser only reads public, aggregate data.

- Each table is a node of flat records, `/{table}/{id}` (e.g. `/print_jobs/42`), with integer ids from `/counters/{table}` so the old ids and foreign keys still work. Dates are ISO-8601 strings, money is a two-decimal string, enums are their value.
- Settings are a key/value map at `/settings/{key}`; notifications are at `/notifications/{uuid}`.
- The Eloquent models use the `rtdb` connection (`app/Realtime`), which evaluates queries in PHP over the repositories in `app/Repositories`. Joins, `GROUP BY`, raw SQL and correlated `whereHas` are not supported; load related models and group collections instead.
- `app/Realtime/Database/TableSchema.php` keeps what the SQL schema enforced: column types and defaults, unique columns and what happens to child records on delete.
- Money and status changes go through `App\Realtime\Atomic`: one atomic multi-path write while a `/locks/...` lock is held.

| Command | What it does |
| --- | --- |
| `firebase:ping [--write]` | Checks the credentials and the database URL. |
| `firebase:seed [--fresh]` | Seeds locations, the admin, the price list and settings (`--fresh` clears everything first). |
| `firebase:import --connection=mysql [--table=...]` | One-time copy of an old MySQL E-Print database into Firebase, keeping ids. |
| `firebase:reconcile [--fix]` | Consistency check (scheduled daily with `--fix`). |

Tests run against an in-memory fake of the Realtime Database, so they need no credentials or network.

## Configuration

| Key | Purpose |
| --- | --- |
| `APP_TIMEZONE` | Defaults to `Asia/Manila`. Used for "today/this week" reports, shift times and the 02:00 cleanup. |
| `ADMIN_USERNAME`, `ADMIN_PASSWORD` | The first admin account, created by the seeder. An existing admin is never overwritten. |
| `EPRINT_DOCX_CONVERSION` | `true` converts DOCX to PDF with LibreOffice to count pages. When `false`, staff enter the page count on Accept. |
| `EPRINT_SOFFICE_PATH` | Path to the LibreOffice `soffice` binary. |

Other settings (commission rate, GCash, retention days, Student ID pattern) are edited in the app under **Settings**.

## Commands and schedule

| Command | Schedule | What it does |
| --- | --- | --- |
| `stations:close-idle` | every minute | Closes stations with no heartbeat for 5 minutes (`timeout`). |
| `files:cleanup` | daily 02:00 | Deletes uploaded files of finished jobs older than the retention period; records are kept. |
| `firebase:reconcile --fix` | daily 03:00 | Checks Realtime Database consistency: repairs id counters, references to deleted records and commission amounts; reports payout totals and claimed jobs without a sale. |
| `sales:import-legacy {file} [--dry-run]` | manual, one time | Imports walk-in sales from the old system (see DEPLOY.md). |

Scheduling lives in `routes/console.php`.

## Tests

```bash
php artisan test          # Pest, in-memory SQLite
./vendor/bin/pint --test  # code style
```

## Code layout

- `app/Http/Controllers/{Auth,Student,Staff,Admin,Api}`: thin controllers.
- `app/Services`: business logic (`PriceCalculator`, `PageCounter`, `DocxConverter`, `ClaimCodeGenerator`, `JobStatusService`, `CommissionService`, `AvailabilityService`, and others).
- `app/Http/Requests`: validation. `app/Policies`: authorization. `app/Http/Middleware`: `EnsureRole`, `EnsureApproved`, `EnsureStation`, `EnsurePasswordChanged`.
- Uploads are stored privately in `storage/app/private/uploads` and served only through `FileController` (owner, staff, admin).

See [DEPLOY.md](DEPLOY.md) for server setup.
