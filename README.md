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
- Claim at pickup with cash or GCash (unique reference number). The sale and commission are recorded in one transaction.
- Record walk-in sales with line items and sheets used; submit own jobs.
- Commission dashboard: today, this week, this month, all time, unpaid balance, own sales list.

**Admins** (everything staff can do, plus)
- Approve, reject or disable accounts; manage staff; reset passwords to a temporary one.
- Price list and settings (commission rate, GCash details and QR, file retention, Student ID pattern).
- Sales report with CSV export, commission payouts, shift history, audit log, announcements.

## Stack

Laravel 12, PHP 8.2+, MySQL 8, Blade + Breeze (Tailwind), Pest, Pint.
Packages: `smalot/pdfparser` (PDF page count) and `simplesoftwareio/simple-qrcode` (claim QR). The front end uses plain JavaScript `fetch` polling, with no WebSockets.

## Local setup

Requirements: PHP 8.2+ with `pdo_mysql`, `pdo_sqlite`, `fileinfo`, `mbstring`, `openssl`, `zip`, `gd` and `bcmath`, plus Composer, Node 18+ and MySQL 8. Laragon works out of the box.

```bash
composer install
npm install && npm run build
cp .env.example .env
php artisan key:generate
# create a MySQL database named "eprint" and set the DB_* values in .env
# set ADMIN_USERNAME / ADMIN_PASSWORD in .env
php artisan migrate --seed
php artisan storage:link
php artisan serve
```

Seeding creates the two locations, the admin account from `ADMIN_USERNAME`/`ADMIN_PASSWORD`, a sample price list (all prices 0; set real prices under **Price List**) and default settings. With `APP_ENV=local` it also creates demo accounts, all with the password `password`: staff `staff1` and `staff2`, and students `MMC2024-00001` and `MMC2024-00002` (approved) and `MMC2024-00003` (pending).

Run the scheduler locally with `php artisan schedule:work`.

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
