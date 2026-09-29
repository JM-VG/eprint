# E-Print Master Prompt

You are the build agent for E-Print, a Laravel 12 web app for the MinSU printing room. This file is your full instruction set. It has two modes. Decide the mode first:

- If STATE.md does NOT exist in the current folder: run PART 1 (Bootstrap), then stop.
- If STATE.md exists: run PART 2 (one Build Iteration), then stop.

Never edit this file (MASTER_PROMPT.md). Never edit docs/SPEC.md. Spec changes come from the human only.

---

## Skill fallback

This loop uses the loop engineering skills: loop-constraints, loop-budget, loop-triage, minimal-fix, loop-verifier, budget-negotiator. Use each skill when installed. When a skill is not installed, follow its rules below.

- loop-constraints: read loop-constraints.md before any work, print "Constraints loaded from loop-constraints.md: N rules active." with the real rule count, exit if loop-pause-all appears in STATE.md, apply every rule to every action.
- loop-budget: at the start, read loop-budget.md and today's entries in loop-run-log.md, sum tokens_estimate for pattern e-print-build. At 80% or more of the daily cap: report-only mode, no code changes. At 90% or more with High Priority items left: run budget-negotiator. At 100%: exit with one line in STATE.md Notes. At the end, append one JSON line to loop-run-log.md: {"run_id":"<ISO8601>","pattern":"e-print-build","duration_s":0,"items_found":0,"actions_taken":0,"escalations":0,"tokens_estimate":0,"outcome":"no-op | report-only | fix-proposed | escalated"} with real values.
- loop-triage: produce a short list with High Priority, Watch, Noise, State Updates. Only real signal. No new architecture.
- minimal-fix: fix one listed problem with the smallest diff, no refactors, run tests, summarize target, diff, verification, risk.
- loop-verifier: a separate sub-agent that did not write the code. Default stance REJECT. Checks: scope (only task-related files, no denylist paths), intent (matches the task's acceptance criteria), tests (runs them itself and quotes the result), no cheating (no skipped, deleted or weakened tests), risk (medium or higher risk means recommend human review). Returns APPROVE, REJECT with numbered reasons, or ESCALATE_HUMAN when tests cannot run.
- budget-negotiator: never raise caps yourself. Add one line tagged [BUDGET NEGOTIATION] to High Priority in STATE.md with spend vs cap, today's actions, the critical item left, and a request of at most +20% or +50k tokens. Then stop. Once per day maximum.

---

## PART 1: Bootstrap (only when STATE.md is missing)

Do NOT build app features in this part.

1. Check tools: php -v (must be 8.2 or higher), composer -V, node -v, npm -v, git --version. Check PHP extensions with php -m: pdo_mysql, pdo_sqlite, fileinfo, mbstring, openssl, zip, gd, bcmath. If PHP is below 8.2 or an extension is missing, stop and tell the human exactly what to install.

2. Create the Laravel project in the current folder. The folder holds MASTER_PROMPT.md, so run composer create-project laravel/laravel:^12.0 _tmp_app, then move every file and folder from _tmp_app (including dotfiles such as .env, .env.example, .gitignore, .editorconfig) into the current folder, then delete _tmp_app.

3. Install Breeze with Blade and Pest: composer require laravel/breeze --dev, then php artisan breeze:install blade --pest. Run npm install and npm run build.

4. Install packages: composer require smalot/pdfparser simplesoftwareio/simple-qrcode.

5. Configure phpunit.xml to run tests on SQLite in memory (DB_CONNECTION=sqlite, DB_DATABASE=:memory:).

6. Update .env.example: DB_CONNECTION=mysql, DB_DATABASE=eprint, plus these keys: ADMIN_USERNAME=admin, ADMIN_PASSWORD=change-me, EPRINT_DOCX_CONVERSION=false, EPRINT_SOFFICE_PATH=soffice. Create config/eprint.php that reads the EPRINT_ keys. Do not edit .env beyond what composer and Breeze created.

7. Copy the section "E-Print Specification" at the end of this file into docs/SPEC.md, unchanged.

8. Create loop-constraints.md with exactly this content:

   # Loop Constraints
   ## Paths
   1. Never edit .env or .env.* files, except .env.example.
   2. Never edit vendor/, node_modules/, or files inside storage/app/private/.
   3. Never edit MASTER_PROMPT.md or docs/SPEC.md.
   4. Never edit a migration already merged to main. Add a new migration instead.
   5. Never edit the caps in loop-budget.md.
   ## Code
   6. One task from STATE.md per iteration. No work outside that task's scope.
   7. Every task ships with Pest tests covering its acceptance criteria.
   8. Never disable, skip, delete or weaken a test to make it pass.
   9. php artisan test and ./vendor/bin/pint --test must pass before commit.
   10. Tasks tagged [HUMAN-REVIEW] get verified, committed, then listed under "Needs human review" in STATE.md.
   ## Push & Merge
   11. Work only on the current phase branch.
   12. Never push. Never merge into main. The human merges each phase.
   13. Commit only after the verifier returns APPROVE.
   ## Escalation
   14. After 3 REJECT verdicts on one task, stop and escalate in STATE.md.
   15. If loop-pause-all appears in STATE.md, exit at once.

9. Create loop-budget.md:

   # Loop Budget
   | Pattern | Max tokens/day | Max sub-agent spawns/run |
   | --- | --- | --- |
   | e-print-build | 3000000 | 3 |
   Kill switch: write loop-pause-all in STATE.md to stop every run.
   ## Alerts This Period

10. Create loop-run-log.md with the single line: # Loop Run Log

11. Create STATE.md with these sections in order: Current phase (write: Phase 1), Phase status, High Priority, Rejections (task id, attempts, reasons), Needs human review, Notes, then the full TASK LIST from docs/SPEC.md as unchecked checkboxes grouped by phase, keeping each task's id, tag and acceptance criteria.

12. Create .claude/commands/build-step.md with this single line of content:
    Read MASTER_PROMPT.md and follow it. STATE.md exists, so run PART 2 (one Build Iteration) exactly as written, then stop.

13. Git: git init, add everything, commit "chore: laravel 12 skeleton, spec and loop files" on branch main. Then git checkout -b phase-1-setup.

14. Run php artisan test and ./vendor/bin/pint --test. Fix only formatting if pint fails, then commit.

15. Report to the human in at most 8 lines: PHP version, what got installed, test result, and these next steps:
    - Create a MySQL database named eprint and set DB_ credentials in .env yourself.
    - Exit Claude Code and start it again in this folder so the /build-step command loads.
    - Run: /loop /build-step

---

## PART 2: Build Iteration (only when STATE.md exists)

Run exactly ONE iteration, then stop.

1. CONSTRAINTS. Apply loop-constraints. Print the rule count line. Exit if loop-pause-all is in STATE.md.

2. BUDGET START. Apply loop-budget with pattern e-print-build.

3. TRIAGE. Apply loop-triage with STATE.md, git status, git log -5 and the last test output as inputs. Pick exactly one target in this priority:
   a. A task under Rejections with fewer than 3 attempts.
   b. The first unchecked task of the current phase.
   c. Every task of the current phase is checked: write "Phase N complete, ready for human review and merge" under Phase status, run BUDGET END, report, and stop. Do nothing more until the human writes "Phase N merged" in STATE.md. When that line exists: git checkout main, create the next phase branch (phase-2-accounts, phase-3-stations, phase-4-jobs, phase-5-queue, phase-6-sales, phase-7-launch), update Current phase, and take its first task.
   d. Nothing actionable: exit in under 5k tokens.

4. BUILD (maker). Read the task in STATE.md and the matching parts of docs/SPEC.md. On the current phase branch:
   - Write Pest tests for every acceptance criterion first, then the code.
   - For a task under Rejections, apply minimal-fix: fix only the listed reasons with the smallest diff.
   - Follow the code layout in the spec: thin controllers, logic in app/Services, validation in Form Requests, authorization in Policies.
   - Run php artisan test and ./vendor/bin/pint. Repeat until both pass.
   - Do not commit yet.

5. VERIFY (checker). Spawn a separate sub-agent that did not write the code. Give it: the task id and acceptance criteria, the output of git diff and git status, the Paths rules from loop-constraints.md, and the commands php artisan test and ./vendor/bin/pint --test. It applies loop-verifier, runs the commands itself, and returns APPROVE, REJECT or ESCALATE_HUMAN with evidence.

6. RECORD.
   - APPROVE: commit as "<task id>: <title>". Tick the task in STATE.md with today's date. Remove it from Rejections. If tagged [HUMAN-REVIEW], add it under Needs human review with the files to read.
   - REJECT: no commit. Keep the working changes. Add or update the task under Rejections with the attempt count and the verifier's reasons. At attempt 3, move it to High Priority as ESCALATED and stop.
   - ESCALATE_HUMAN: no commit. Add it to High Priority with the verifier's note and stop.
   - Commit the STATE.md update in the same commit on APPROVE, or as "chore: loop state" otherwise.

7. BUDGET END. Apply loop-budget and append the run's JSON line to loop-run-log.md.

8. REPORT in at most 6 lines: task id, verdict, tests passed/total, files changed, next task, anything the human needs to do.

---

## E-Print Specification

### Purpose
Online print request and sales system for the MinSU printing room with two locations: Main IT Printing Room and CCS Office. Students upload print jobs from anywhere and pick up at the counter. Staff (business managers) clock in at a location, work the queue, collect payment and record walk-in sales. Every staff member sees their commission. One central database replaces the old per-PC system.

### Stack
Laravel 12, PHP 8.2+, MySQL, Blade + Breeze (Tailwind), Pest tests, Pint. Packages: smalot/pdfparser (PDF page count), simplesoftwareio/simple-qrcode (claim QR). Vanilla JS fetch polling, no WebSockets.

### Roles
- student: signs up, waits for approval, submits print and photo jobs, edits or deletes own jobs while Pending, tracks status.
- staff: clocks in at a station, processes queue, collects cash or GCash at pickup, records walk-in sales, submits own jobs, views own commission.
- admin (super admin): everything staff do, plus approve students, manage staff, prices, settings, commission rate, reports, payouts, announcements.
Role comes from the account. Only admin creates staff accounts.

### Accounts
- Student signup fields: student_id, first_name, last_name, password, password_confirmation.
- student_id must match the pattern in settings (default ^[A-Z]{3}\d{4}-\d{5}$, example MMC2024-00123). Store uppercase.
- Duplicate student_id error message: "This Student ID is already registered. If this is your ID, visit the printing room."
- New students start with status pending. They see a waiting page and cannot submit jobs. Admins get a NewSignup notification.
- Admin approves or rejects (with reason). Student gets AccountApproved or AccountRejected notification. Admin can disable any account.
- Login: one "Student ID or Username" field. Students log in by student_id, staff and admin by username. Throttle 5 failures per 15 minutes. Pending, rejected and disabled accounts get a clear message and no session.
- Admin resets a password to a temporary one; must_change_password forces a change on next login.

### Stations and availability
- Staff press Clock In and pick a location. This creates a station_session (opened_at, last_seen_at).
- Staff routes that process jobs require an open station (EnsureStation middleware). Admin pages for reports and settings do not.
- Staff pages send POST /api/heartbeat every 30 seconds, updating last_seen_at.
- Clock Out, logout, or Switch Location close the session (close_reason manual, logout, switch).
- CloseIdleStations command, scheduled every minute, closes sessions with last_seen_at older than 5 minutes (close_reason timeout).
- A location is Open when at least one session there has closed_at null and last_seen_at within 5 minutes.
- GET /api/availability returns both locations with open flag and count of staff. A banner component on every page, including login, polls it every 15 seconds.
- Admin Shift History page: filter by staff, location, date range; shows opened_at, closed_at, duration, close_reason.

### Print jobs
- Types: document (PDF, DOCX, JPG, PNG) and photo (JPG, PNG). Max 20 MB. One file per job.
- Document options: location, service (paper size + color from price list), copies, page_range (blank = all), duplex, notes.
- Photo options: location, photo service (1x1, 2x2, passport), sets, notes.
- Files stored on the private local disk under uploads/ with random names. Served only through FileController after authorization (owner, staff, admin).
- Page count: PDF via smalot/pdfparser. DOCX converted to PDF with LibreOffice when EPRINT_DOCX_CONVERSION=true, then counted; otherwise page_count stays null and staff enter it on Accept. Images count as 1 page.
- Price estimate shown before submit via POST /api/price-estimate. Price snapshot saved on the job (unit_price, estimated_total).
- A closed location still accepts jobs with the note: "<Location> is closed. Your job waits in the queue until staff open the station."
- Claim code format EP-XXXX (4 chars from ABCDEFGHJKMNPQRSTUVWXYZ23456789), unique. Job page shows code and QR.
- Statuses: pending, accepted, printing, ready, claimed, rejected, cancelled.
  Allowed: pending->accepted|rejected|cancelled, accepted->printing|rejected|cancelled, printing->ready, ready->claimed. Anything else throws.
- Accept requires final page_count and final_total. Reject requires a reason. Every change writes a job_logs row.
- Student edits or deletes own job only while pending (PrintJobPolicy). Delete removes the file.
- Staff queue: jobs for current location first, oldest first, tab for the other location, badge count polled every 15 seconds.
- JobReady notification to the student when status becomes ready. In-app bell with unread count.
- Staff and admin submit jobs through the same form; these jobs carry source staff_job.

### Pricing and settings
- services table is the price list, one list for both locations: type (document, photo), name, paper_size, color_mode, unit (page, set), pieces_per_set, price decimal(10,2), is_active. Admin CRUD.
- Document total = pages printed x copies x unit price. Photo total = sets x unit price.
- settings (key-value, cached): commission_rate (percent), gcash_name, gcash_number, gcash_qr_path, retention_days (7), student_id_pattern.

### Payments and sales
- Both cash and GCash are paid at pickup. Claim screen: staff enter or scan the claim code, see the final total, pick Cash or GCash.
- GCash requires a reference_no, unique across gcash_payments.
- On claim: job becomes claimed + paid, a sales row is created by CommissionService in one DB transaction.
- Sale credit: online student job -> staff who received payment. staff_job -> staff who submitted it. walk-in -> staff who entered it.
- Sale stores amount, commission_rate snapshot, commission_amount = round(amount x rate / 100, 2), location_id, source (online, walkin, staff_job).
- Walk-in sale form: one or more line items (service, quantity, sheets_used), amount prefilled from price list and editable, payment method.
- Staff dashboard: commission today, this week, this month, all time; unpaid commission balance; jobs handled; own sales list with date and location filters.
- Admin payouts: pick staff and date range, sum unpaid commission, create commission_payouts row, link sales via payout_id.

### Reports
- Sales report filters: timeframe (today, this week, this month, custom range), officer, location, source. Shows totals for amount, commission, sheets used.
- CSV export of the filtered report (streamed response).
- Audit log page for job_logs.
- Optional one-time CSV import of legacy walk-in sales.

### Other
- Announcements with starts_at and ends_at, shown as a banner.
- CleanupOldFiles command daily at 02:00: deletes files of jobs older than retention_days, sets files_deleted_at, keeps records.
- Scheduling lives in routes/console.php.

### Database tables
users (add: student_id unique nullable, username unique nullable, first_name, last_name, role, status, must_change_password, approved_by, approved_at, rejection_reason), locations, station_sessions, services, print_jobs (claim_code, user_id, location_id, service_id, source, original_name, stored_path, pdf_path, file_type, file_size, page_count, page_range, copies, duplex, sets, notes, unit_price, estimated_total, final_total, sheets_used, status, payment_method, payment_status, reject_reason, received_by, claimed_at, files_deleted_at), gcash_payments, sales, walkin_items, commission_payouts, job_logs, settings, announcements, notifications (Laravel default).
Use PHP backed enums: Role, UserStatus, JobStatus, PaymentMethod, PaymentStatus, SaleSource, CloseReason. Money columns decimal(10,2).

### Code layout
Controllers under Http/Controllers/{Auth,Student,Staff,Admin,Api}. Business logic in app/Services (PriceCalculator, PageCounter, DocxConverter, ClaimCodeGenerator, JobStatusService, CommissionService, AvailabilityService). Validation in Form Requests. Authorization in Policies. Middleware: EnsureRole, EnsureApproved, EnsureStation, EnsurePasswordChanged.

### TASK LIST
Each task: id, title, acceptance criteria. Tag [HUMAN-REVIEW] where marked.

#### Phase 1 Setup (branch phase-1-setup)
- P1-01 Migrations for users changes, locations, station_sessions. AC: migrate:fresh runs; columns match spec.
- P1-02 Migrations for services, print_jobs, gcash_payments, sales, walkin_items, commission_payouts, job_logs, settings, announcements, notifications. AC: migrate:fresh runs; foreign keys set.
- P1-03 Enums, models, relationships, casts, factories. AC: factory tests create every model with relations.
- P1-04 Seeders: 2 locations, admin from ADMIN_USERNAME/ADMIN_PASSWORD, sample services with price 0, default settings. DemoSeeder for local only. AC: db:seed works twice without duplicates.
- P1-05 Layout: role-based sidebar, availability banner placeholder component, flash messages. AC: each role sees only its menu.

#### Phase 2 Accounts (branch phase-2-accounts)
- P2-01 [HUMAN-REVIEW] Student signup with pattern, uppercase, duplicate message, pending status, NewSignup notification.
- P2-02 [HUMAN-REVIEW] Login by student_id or username, throttle, status messages, role redirect.
- P2-03 [HUMAN-REVIEW] EnsureRole, EnsureApproved, EnsurePasswordChanged middleware, route groups, 403 page.
- P2-04 Admin student approval queue: approve, reject with reason, disable, notifications.
- P2-05 [HUMAN-REVIEW] Admin staff management: create, edit, disable, temporary password reset.
- P2-06 Profile page with password change.

#### Phase 3 Stations (branch phase-3-stations)
- P3-01 Clock In page, station_session create, EnsureStation middleware.
- P3-02 Heartbeat endpoint, Clock Out, Switch Location, close on logout.
- P3-03 AvailabilityService + CloseIdleStations command scheduled every minute.
- P3-04 GET /api/availability + banner with 15 s polling on every page including login.
- P3-05 Admin Shift History with filters.

#### Phase 4 Jobs (branch phase-4-jobs)
- P4-01 Admin price list CRUD and Settings page (commission rate, GCash details and QR upload, retention days, student ID pattern).
- P4-02 PriceCalculator with unit tests for page ranges, copies, photo sets.
- P4-03 PageCounter and DocxConverter with fixture tests; conversion off by config.
- P4-04 [HUMAN-REVIEW] Student document job form: upload validation, private storage, estimate endpoint, closed-location note, claim code.
- P4-05 Photo job form.
- P4-06 My Jobs, Job Details with QR, edit and delete while pending via PrintJobPolicy.
- P4-07 [HUMAN-REVIEW] FileController with authorization tests for owner, other student, staff, admin.

#### Phase 5 Queue (branch phase-5-queue)
- P5-01 Staff queue per location with tabs and polled badge.
- P5-02 JobStatusService with allowed transitions, job_logs, accept and reject rules.
- P5-03 JobReady notification and in-app bell with unread count endpoint.
- P5-04 Staff and admin job submission with source staff_job.

#### Phase 6 Payments and sales (branch phase-6-sales)
- P6-01 [HUMAN-REVIEW] Claim flow with cash and GCash, unique reference, CommissionService, credit rules, transaction.
- P6-02 [HUMAN-REVIEW] Walk-in sale form with line items and sheets_used.
- P6-03 Staff commission dashboard.
- P6-04 [HUMAN-REVIEW] Admin payouts.

#### Phase 7 Reports and launch (branch phase-7-launch)
- P7-01 Sales report filters and CSV export.
- P7-02 Announcements CRUD and banner.
- P7-03 Student and admin dashboards.
- P7-04 CleanupOldFiles command and schedule.
- P7-05 Audit log page.
- P7-06 Legacy walk-in CSV import (optional).
- P7-07 README.md, DEPLOY.md (server checklist, cron line, php.ini limits, LibreOffice note), final full test run.
