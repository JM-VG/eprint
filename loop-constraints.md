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
