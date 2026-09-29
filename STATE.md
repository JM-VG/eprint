# E-Print Build State

## Current phase
Phase 1

## Phase status
Phase 1 complete, ready for human review and merge

## High Priority
(none)

## Rejections
| Task id | Attempts | Reasons |
| --- | --- | --- |

## Needs human review
(none)

## Notes
- Bootstrap completed 2026-09-30.
- P1-01 verifier note: users.name is still NOT NULL; student signup (P2-01) must fill name or add a migration making it nullable.
- P1-02 verifier note: print_jobs.user_id cascades on user delete (Breeze self-delete wipes a student's job history; sales survive). gcash_payments.sale_id cascades with its sale. Revisit when policies land (disable accounts instead of deleting).
- P1-03 verifier note: User role/status/approved_by/must_change_password are mass-assignable; P2 controllers must only pass validated or explicit arrays.
- P1-04 verifier notes: seeded admin is not must_change_password (consider in P2-05); commission_rate seeded as 0 (admin must set); DEPLOY.md (P7-07) must say APP_ENV=production or DemoSeeder creates accounts with password "password".
- P1-05 note: sidebar menu items point at planned route names (app/View/Components/Sidebar.php); items stay disabled until a route with that exact name exists. Align names when building each page.

## Task List

### Phase 1 Setup (branch phase-1-setup)
- [x] P1-01 Migrations for users changes, locations, station_sessions. AC: migrate:fresh runs; columns match spec. (done 2026-09-30)
- [x] P1-02 Migrations for services, print_jobs, gcash_payments, sales, walkin_items, commission_payouts, job_logs, settings, announcements, notifications. AC: migrate:fresh runs; foreign keys set. (done 2026-09-30)
- [x] P1-03 Enums, models, relationships, casts, factories. AC: factory tests create every model with relations. (done 2026-09-30)
- [x] P1-04 Seeders: 2 locations, admin from ADMIN_USERNAME/ADMIN_PASSWORD, sample services with price 0, default settings. DemoSeeder for local only. AC: db:seed works twice without duplicates. (done 2026-09-30)
- [x] P1-05 Layout: role-based sidebar, availability banner placeholder component, flash messages. AC: each role sees only its menu. (done 2026-09-30)

### Phase 2 Accounts (branch phase-2-accounts)
- [ ] P2-01 [HUMAN-REVIEW] Student signup with pattern, uppercase, duplicate message, pending status, NewSignup notification.
- [ ] P2-02 [HUMAN-REVIEW] Login by student_id or username, throttle, status messages, role redirect.
- [ ] P2-03 [HUMAN-REVIEW] EnsureRole, EnsureApproved, EnsurePasswordChanged middleware, route groups, 403 page.
- [ ] P2-04 Admin student approval queue: approve, reject with reason, disable, notifications.
- [ ] P2-05 [HUMAN-REVIEW] Admin staff management: create, edit, disable, temporary password reset.
- [ ] P2-06 Profile page with password change.

### Phase 3 Stations (branch phase-3-stations)
- [ ] P3-01 Clock In page, station_session create, EnsureStation middleware.
- [ ] P3-02 Heartbeat endpoint, Clock Out, Switch Location, close on logout.
- [ ] P3-03 AvailabilityService + CloseIdleStations command scheduled every minute.
- [ ] P3-04 GET /api/availability + banner with 15 s polling on every page including login.
- [ ] P3-05 Admin Shift History with filters.

### Phase 4 Jobs (branch phase-4-jobs)
- [ ] P4-01 Admin price list CRUD and Settings page (commission rate, GCash details and QR upload, retention days, student ID pattern).
- [ ] P4-02 PriceCalculator with unit tests for page ranges, copies, photo sets.
- [ ] P4-03 PageCounter and DocxConverter with fixture tests; conversion off by config.
- [ ] P4-04 [HUMAN-REVIEW] Student document job form: upload validation, private storage, estimate endpoint, closed-location note, claim code.
- [ ] P4-05 Photo job form.
- [ ] P4-06 My Jobs, Job Details with QR, edit and delete while pending via PrintJobPolicy.
- [ ] P4-07 [HUMAN-REVIEW] FileController with authorization tests for owner, other student, staff, admin.

### Phase 5 Queue (branch phase-5-queue)
- [ ] P5-01 Staff queue per location with tabs and polled badge.
- [ ] P5-02 JobStatusService with allowed transitions, job_logs, accept and reject rules.
- [ ] P5-03 JobReady notification and in-app bell with unread count endpoint.
- [ ] P5-04 Staff and admin job submission with source staff_job.

### Phase 6 Payments and sales (branch phase-6-sales)
- [ ] P6-01 [HUMAN-REVIEW] Claim flow with cash and GCash, unique reference, CommissionService, credit rules, transaction.
- [ ] P6-02 [HUMAN-REVIEW] Walk-in sale form with line items and sheets_used.
- [ ] P6-03 Staff commission dashboard.
- [ ] P6-04 [HUMAN-REVIEW] Admin payouts.

### Phase 7 Reports and launch (branch phase-7-launch)
- [ ] P7-01 Sales report filters and CSV export.
- [ ] P7-02 Announcements CRUD and banner.
- [ ] P7-03 Student and admin dashboards.
- [ ] P7-04 CleanupOldFiles command and schedule.
- [ ] P7-05 Audit log page.
- [ ] P7-06 Legacy walk-in CSV import (optional).
- [ ] P7-07 README.md, DEPLOY.md (server checklist, cron line, php.ini limits, LibreOffice note), final full test run.
