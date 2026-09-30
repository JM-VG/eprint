# Deploying E-Print

## Server checklist

- [ ] Linux server (Ubuntu 22.04+ recommended) with Nginx or Apache and HTTPS.
- [ ] PHP 8.2 or newer (PHP-FPM) with extensions: `pdo_mysql`, `fileinfo`, `mbstring`, `openssl`, `zip`, `gd`, `bcmath`, `xml`, `curl`, `intl`.
- [ ] MySQL 8 with an `eprint` database (utf8mb4) and a dedicated user.
- [ ] Composer 2 and Node 18+ (Node is only needed to build assets).
- [ ] The web root points to the project's `public/` directory, never the project root.
- [ ] `storage/` and `bootstrap/cache/` are writable by the web server user (e.g. `www-data`).
- [ ] Cron is installed (for the scheduler).
- [ ] Optional: LibreOffice for DOCX page counting (see below).

## First deploy

```bash
git clone https://github.com/JM-VG/eprint.git /var/www/eprint
cd /var/www/eprint
composer install --no-dev --optimize-autoloader
npm ci && npm run build
cp .env.example .env
php artisan key:generate
```

Edit `.env`:

```dotenv
APP_ENV=production        # IMPORTANT: with "local" the seeder creates demo accounts using the password "password"
APP_DEBUG=false
APP_URL=https://eprint.example.edu.ph
APP_TIMEZONE=Asia/Manila

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_DATABASE=eprint
DB_USERNAME=eprint
DB_PASSWORD=********

ADMIN_USERNAME=admin
ADMIN_PASSWORD=a-long-unique-password   # change "change-me"; sign in and change it again afterwards

EPRINT_DOCX_CONVERSION=false
EPRINT_SOFFICE_PATH=/usr/bin/soffice
```

Then:

```bash
php artisan migrate --force
php artisan db:seed --force      # locations, admin, sample price list (prices 0), default settings
php artisan storage:link         # public disk, used for the GCash QR image
php artisan config:cache
php artisan route:cache
php artisan view:cache
sudo chown -R www-data:www-data storage bootstrap/cache
```

After the first login as admin:
1. Set real prices under **Price List** (the seeded prices are 0).
2. Set the **commission rate** (seeded as 0), the GCash name, number and QR, the retention days and the Student ID pattern under **Settings**.
3. Create staff accounts under **Staff**. Each gets a temporary password they must change.

## Cron line

The scheduler closes idle stations every minute and cleans old files daily at 02:00:

```cron
* * * * * cd /var/www/eprint && php artisan schedule:run >> /dev/null 2>&1
```

Add it with `sudo crontab -u www-data -e`. The schedule uses `withoutOverlapping()`, which needs a cache store that supports locks. The default `database` cache store (its table is created by the migrations) works, and so do `redis` and `file`.

## php.ini limits

Uploads can be up to 20 MB, so allow some headroom:

```ini
upload_max_filesize = 25M
post_max_size = 30M
memory_limit = 256M
max_execution_time = 120   ; DOCX conversion can take up to 60 s
```

For Nginx also set `client_max_body_size 30M;` in the server block. Restart PHP-FPM and the web server after changing these.

## LibreOffice note (DOCX page count)

PDF page counts are read directly. For DOCX files there are two options:

- **Conversion off** (`EPRINT_DOCX_CONVERSION=false`, the default): DOCX jobs have no page count or estimated total until staff enter the page count on **Accept**.
- **Conversion on**: install LibreOffice and enable it:

  ```bash
  sudo apt install --no-install-recommends libreoffice-writer fonts-dejavu fonts-liberation
  which soffice        # put this path in EPRINT_SOFFICE_PATH
  ```

  Set `EPRINT_DOCX_CONVERSION=true`. LibreOffice needs a writable profile directory for the PHP user. If conversion fails, give `www-data` a home directory it can write to (e.g. `sudo mkdir -p /var/www/.config && sudo chown www-data /var/www/.config`). Conversions time out after 60 seconds, and a failed conversion falls back to staff entering the page count.

## Legacy sales import (optional, one time)

Export walk-in sales from the old system as CSV with this exact header:

```csv
date,officer,location,service,quantity,sheets_used,amount,payment_method,reference_no,commission_rate
2025-06-01 09:15,staff1,main-it,Short Black & White,10,10,20.00,cash,,
2025-06-02 14:00,staff1,ccs-office,,1,3,15.50,gcash,1112223334445,12.5
```

- `officer` is a staff or admin username, `location` is `main-it` or `ccs-office`, and `service` is optional (a price list name).
- `reference_no` is required for GCash. `commission_rate` is optional (blank uses the current setting).

```bash
php artisan sales:import-legacy storage/legacy-sales.csv --dry-run   # validate only
php artisan sales:import-legacy storage/legacy-sales.csv
```

One invalid row stops the whole import, and errors are reported by line number. Re-running the same file skips rows that were already imported.

## Updating

```bash
php artisan down
git pull
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan migrate --force
php artisan config:cache && php artisan route:cache && php artisan view:cache
php artisan up
```

## Backups

Back up the MySQL database daily and `storage/app/private/uploads` (student files, kept for the retention period). `storage/app/public/gcash` holds the GCash QR image.

## Post-deploy check

- [ ] `/login` loads over HTTPS and shows the availability banner.
- [ ] The admin can sign in, and a test student can sign up and be approved.
- [ ] A staff member can clock in, and the banner shows the location as open within 15 s.
- [ ] A PDF upload shows the page count and estimate, and the file opens from the queue.
- [ ] `php artisan schedule:list` shows `stations:close-idle` (every minute) and `files:cleanup` (02:00).
- [ ] `APP_ENV=production` and `APP_DEBUG=false`.
