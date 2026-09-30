# E-Print Build State

## Current phase
Phase 6

## Phase status
Phase 1 complete, reviewed by Claude (MySQL rollback bug found and fixed in 985bda1)
Phase 1 merged (2026-09-30, merged by Claude at the human's request: "i want you to review it yourself ... i give you all permission")
Phase 2 complete, reviewed by Claude: 184 tests pass; MySQL 8 migrate:fresh --seed, re-seed and migrate:reset clean (scratch DB eprint_phase_review).
Phase 2 merged (2026-09-30, merged by Claude at the human's request)
Phase 3 complete, reviewed by Claude: 244 tests pass; MySQL 8 migrate/seed/clock-in/availability/idle-close/shift-history/reset clean. Review fix: APP_TIMEZONE defaults to Asia/Manila.
Phase 3 merged (2026-09-30, merged by Claude at the human's request)
Phase 4 complete, reviewed by Claude: 398 tests pass, Pint clean. MySQL re-check skipped (server was stopped for low memory); Phase 4 added no migrations, schema last verified on MySQL 8 at Phase 3.
Phase 4 merged (2026-09-30, merged by Claude at the human's request)
Phase 5 complete, reviewed by Claude: 494 tests pass, Pint clean; no migrations in Phase 5 (MySQL schema unchanged since Phase 3 check).
Phase 5 merged (2026-09-30, merged by Claude at the human's request)
Phase 6 in progress.

## High Priority
(none)

## Rejections
| Task id | Attempts | Reasons |
| --- | --- | --- |

## Needs human review
- P2-01 Student signup: app/Services/StudentRegistrationService.php, app/Http/Requests/Auth/StudentRegisterRequest.php, app/Http/Controllers/Auth/RegisteredUserController.php, app/Models/Setting.php, app/Notifications/NewSignup.php, resources/views/auth/register.blade.php, resources/views/auth/registration-pending.blade.php, routes/auth.php, tests/Feature/Auth/RegistrationTest.php
- P2-02 Login: app/Http/Requests/Auth/LoginRequest.php, app/Http/Controllers/Auth/AuthenticatedSessionController.php, app/Enums/UserStatus.php (message wording), app/Enums/Role.php, tests/Feature/Auth/AuthenticationTest.php
- P2-03 Middleware: app/Http/Middleware/EnsureRole.php, app/Http/Middleware/EnsureApproved.php, app/Http/Middleware/EnsurePasswordChanged.php (ALLOWED_ROUTES), bootstrap/app.php, routes/web.php, app/Http/Controllers/Auth/PasswordController.php, resources/views/errors/403.blade.php, tests/Feature/Auth/AccessMiddlewareTest.php
- P2-05 Staff management: app/Http/Requests/Admin/StaffRequest.php, app/Services/StaffAccountService.php, app/Http/Controllers/Admin/UserAccountController.php, app/Http/Controllers/Admin/StaffController.php, app/Policies/UserPolicy.php, app/Services/AccountApprovalService.php, routes/web.php, resources/views/admin/staff/*.blade.php, tests/Feature/Admin/StaffManagementTest.php
- P4-04 Document job form: app/Services/JobSubmissionService.php, app/Http/Requests/Student/StoreDocumentJobRequest.php, app/Http/Controllers/Api/PriceEstimateController.php, app/Services/ClaimCodeGenerator.php, routes/web.php

- P4-07 FileController: app/Http/Controllers/FileController.php, tests/Feature/FileAccessTest.php, config/filesystems.php (local serve => false), app/Policies/PrintJobPolicy.php

- P6-01 Claim flow: app/Services/CommissionService.php, app/Http/Controllers/Staff/ClaimController.php, app/Http/Requests/Staff/ClaimRequest.php, tests/Feature/Staff/ClaimTest.php, app/Services/JobStatusService.php

- P6-02 Walk-in sales: app/Services/CommissionService.php (recordWalkin), app/Http/Requests/Staff/WalkinSaleRequest.php, app/Http/Controllers/Staff/WalkinSaleController.php, resources/js/walkin.js, tests/Feature/Staff/WalkinSaleTest.php

- P6-04 Payouts: app/Services/PayoutService.php, app/Http/Controllers/Admin/PayoutController.php, app/Http/Requests/Admin/PayoutRequest.php, tests/Feature/Admin/PayoutTest.php

## Notes
- 2026-09-30: human instructed to continue past the report-only threshold: "lets finish this project. continue finish it". Caps in loop-budget.md left unchanged; build/verify/commit cycle continues per task.
- 2026-09-30: daily budget at 82.5% (2,474,000 of 3,000,000 logged) after P5-02; loop switched to report-only. Next task: P5-03. Raise Max tokens/day in loop-budget.md (human only) or resume after the daily reset.
- Bootstrap completed 2026-09-30.
- P1-01 verifier note: users.name is still NOT NULL; student signup (P2-01) must fill name or add a migration making it nullable.
- P1-02 verifier note: print_jobs.user_id cascades on user delete (Breeze self-delete wipes a student's job history; sales survive). gcash_payments.sale_id cascades with its sale. Revisit when policies land (disable accounts instead of deleting).
- P1-03 verifier note: User role/status/approved_by/must_change_password are mass-assignable; P2 controllers must only pass validated or explicit arrays.
- P1-04 verifier notes: seeded admin is not must_change_password (consider in P2-05); commission_rate seeded as 0 (admin must set); DEPLOY.md (P7-07) must say APP_ENV=production or DemoSeeder creates accounts with password "password".
- P2-01 verifier note: Setting cache is flushed via model events only; the P4-01 settings page must save through Setting models, not bulk query updates.
- P2-02 verifier notes: P2-05 staff creation must reject a username equal (case-insensitive) to any student_id, since login tries student_id first. intended() redirects are only safe once P2-03 EnsureRole exists.
- P2-03 notes: EnsureApproved and EnsurePasswordChanged run on the whole web group. student./staff./admin. route groups in routes/web.php are empty; each later task must add its routes inside the right group and test role access.
- P4-04 verifier notes: config/filesystems.php local disk has 'serve' => true; P4-07 should set it false so uploads are only reachable via FileController. Claim-code check-then-insert can race (unique index blocks it, user sees an error); consider retry on unique violation. Submit route has no rate limit.
- P4-07 verifier note: an original_name with control characters (newline/tab) makes the download 500 (fails closed); consider stripping control chars at upload.
- P5-02 verifier notes: AcceptJobRequest requires page_count even for photo (sets) jobs; accept() computes sheets_used before the row lock; no test for accepting a DOCX with null page_count.
- P1-05 note: sidebar menu items point at planned route names (app/View/Components/Sidebar.php); items stay disabled until a route with that exact name exists. Align names when building each page.

## Task List

### Phase 1 Setup (branch phase-1-setup)
- [x] P1-01 Migrations for users changes, locations, station_sessions. AC: migrate:fresh runs; columns match spec. (done 2026-09-30)
- [x] P1-02 Migrations for services, print_jobs, gcash_payments, sales, walkin_items, commission_payouts, job_logs, settings, announcements, notifications. AC: migrate:fresh runs; foreign keys set. (done 2026-09-30)
- [x] P1-03 Enums, models, relationships, casts, factories. AC: factory tests create every model with relations. (done 2026-09-30)
- [x] P1-04 Seeders: 2 locations, admin from ADMIN_USERNAME/ADMIN_PASSWORD, sample services with price 0, default settings. DemoSeeder for local only. AC: db:seed works twice without duplicates. (done 2026-09-30)
- [x] P1-05 Layout: role-based sidebar, availability banner placeholder component, flash messages. AC: each role sees only its menu. (done 2026-09-30)

### Phase 2 Accounts (branch phase-2-accounts)
- [x] P2-01 [HUMAN-REVIEW] Student signup with pattern, uppercase, duplicate message, pending status, NewSignup notification. (done 2026-09-30)
- [x] P2-02 [HUMAN-REVIEW] Login by student_id or username, throttle, status messages, role redirect. (done 2026-09-30)
- [x] P2-03 [HUMAN-REVIEW] EnsureRole, EnsureApproved, EnsurePasswordChanged middleware, route groups, 403 page. (done 2026-09-30)
- [x] P2-04 Admin student approval queue: approve, reject with reason, disable, notifications. (done 2026-09-30)
- [x] P2-05 [HUMAN-REVIEW] Admin staff management: create, edit, disable, temporary password reset. (done 2026-09-30)
- [x] P2-06 Profile page with password change. (done 2026-09-30)

### Phase 3 Stations (branch phase-3-stations)
- [x] P3-01 Clock In page, station_session create, EnsureStation middleware. (done 2026-09-30)
- [x] P3-02 Heartbeat endpoint, Clock Out, Switch Location, close on logout. (done 2026-09-30)
- [x] P3-03 AvailabilityService + CloseIdleStations command scheduled every minute. (done 2026-09-30)
- [x] P3-04 GET /api/availability + banner with 15 s polling on every page including login. (done 2026-09-30)
- [x] P3-05 Admin Shift History with filters. (done 2026-09-30)

### Phase 4 Jobs (branch phase-4-jobs)
- [x] P4-01 Admin price list CRUD and Settings page (commission rate, GCash details and QR upload, retention days, student ID pattern). (done 2026-09-30)
- [x] P4-02 PriceCalculator with unit tests for page ranges, copies, photo sets. (done 2026-09-30)
- [x] P4-03 PageCounter and DocxConverter with fixture tests; conversion off by config. (done 2026-09-30)
- [x] P4-04 [HUMAN-REVIEW] Student document job form: upload validation, private storage, estimate endpoint, closed-location note, claim code. (done 2026-09-30)
- [x] P4-05 Photo job form. (done 2026-09-30)
- [x] P4-06 My Jobs, Job Details with QR, edit and delete while pending via PrintJobPolicy. (done 2026-09-30)
- [x] P4-07 [HUMAN-REVIEW] FileController with authorization tests for owner, other student, staff, admin. (done 2026-09-30)

### Phase 5 Queue (branch phase-5-queue)
- [x] P5-01 Staff queue per location with tabs and polled badge. (done 2026-09-30)
- [x] P5-02 JobStatusService with allowed transitions, job_logs, accept and reject rules. (done 2026-09-30)
- [x] P5-03 JobReady notification and in-app bell with unread count endpoint. (done 2026-09-30)
- [x] P5-04 Staff and admin job submission with source staff_job. (done 2026-09-30)

### Phase 6 Payments and sales (branch phase-6-sales)
- [x] P6-01 [HUMAN-REVIEW] Claim flow with cash and GCash, unique reference, CommissionService, credit rules, transaction. (done 2026-09-30)
- [x] P6-02 [HUMAN-REVIEW] Walk-in sale form with line items and sheets_used. (done 2026-09-30)
- [x] P6-03 Staff commission dashboard. (done 2026-09-30)
- [x] P6-04 [HUMAN-REVIEW] Admin payouts. (done 2026-09-30)

### Phase 7 Reports and launch (branch phase-7-launch)
- [ ] P7-01 Sales report filters and CSV export.
- [ ] P7-02 Announcements CRUD and banner.
- [ ] P7-03 Student and admin dashboards.
- [ ] P7-04 CleanupOldFiles command and schedule.
- [ ] P7-05 Audit log page.
- [ ] P7-06 Legacy walk-in CSV import (optional).
- [ ] P7-07 README.md, DEPLOY.md (server checklist, cron line, php.ini limits, LibreOffice note), final full test run.
