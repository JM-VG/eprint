<?php

use App\Enums\JobStatus;
use App\Models\Location;
use App\Models\PrintJob;
use App\Models\Sale;
use App\Models\StationSession;
use App\Models\User;

beforeEach(function () {
    $this->main = Location::factory()->create(['name' => 'Main IT Printing Room']);
    $this->ccs = Location::factory()->create(['name' => 'CCS Office']);
});

describe('student dashboard', function () {
    it('shows own job counts, ready jobs and recent jobs only', function () {
        $student = User::factory()->create();
        PrintJob::factory()->for($this->main)->create(['user_id' => $student->id, 'status' => JobStatus::Pending, 'claim_code' => 'EP-PEND']);
        PrintJob::factory()->for($this->main)->create(['user_id' => $student->id, 'status' => JobStatus::Ready, 'claim_code' => 'EP-REDY']);
        PrintJob::factory()->for($this->main)->create(['user_id' => $student->id, 'status' => JobStatus::Claimed, 'claim_code' => 'EP-DONE']);
        PrintJob::factory()->create(['status' => JobStatus::Ready, 'claim_code' => 'EP-OTHR']);

        $this->actingAs($student)
            ->get('/dashboard')
            ->assertOk()
            ->assertViewHas('summary', fn (array $s) => $s['active'] === 2 && $s['ready'] === 1 && $s['completed'] === 1)
            ->assertSee('EP-REDY')
            ->assertSee('ready for pickup')
            ->assertSee('EP-PEND')
            ->assertDontSee('EP-OTHR')
            ->assertSee(route('student.jobs.create'), false);
    });
});

describe('staff dashboard', function () {
    it('shows the station, pending jobs there and today\'s commission', function () {
        $staff = User::factory()->staff()->create();
        StationSession::factory()->for($staff)->for($this->ccs)->create(['last_seen_at' => now()]);
        PrintJob::factory()->for($this->ccs)->count(2)->create();
        PrintJob::factory()->for($this->main)->create();
        Sale::factory()->create(['user_id' => $staff->id, 'location_id' => $this->ccs->id, 'commission_amount' => '3.25']);

        $this->actingAs($staff)
            ->get('/dashboard')
            ->assertOk()
            ->assertViewHas('summary', fn (array $s) => $s['station'] === 'CCS Office'
                && $s['pending_here'] === 2
                && $s['commission_today'] === '3.25');
    });
});

describe('admin dashboard', function () {
    it('is admin only', function (string $state) {
        $this->actingAs(User::factory()->{$state}()->create())->get(route('admin.dashboard'))->assertForbidden();
    })->with(['student', 'staff']);

    it('shows today\'s sales, pending approvals, jobs by status, open locations and unpaid commission', function () {
        $admin = User::factory()->admin()->create();
        User::factory()->pending()->count(3)->create();
        StationSession::factory()->for($this->main)->create(['last_seen_at' => now()]);
        PrintJob::factory()->for($this->main)->count(2)->create(['status' => JobStatus::Pending]);
        PrintJob::factory()->for($this->ccs)->create(['status' => JobStatus::Ready]);
        Sale::factory()->create(['amount' => '45.50', 'commission_amount' => '4.55', 'location_id' => $this->main->id]);
        Sale::factory()->create(['amount' => '20.00', 'commission_amount' => '2.00', 'location_id' => $this->ccs->id]);
        Sale::factory()->create(['amount' => '99.00', 'commission_amount' => '9.90', 'location_id' => $this->ccs->id, 'created_at' => now()->subDays(2)]);

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertViewHas('summary', fn (array $s) => $s['sales_today']['amount'] === '65.50'
                && $s['sales_today']['count'] === 2
                && $s['sales_today']['commission'] === '6.55'
                && $s['pending_students'] === 3
                && $s['jobs']['pending'] === 2
                && $s['jobs']['ready'] === 1
                && $s['unpaid_commission'] === '16.45')
            ->assertSee('Main IT Printing Room')
            ->assertSee(route('admin.students.index'), false);
    });

    it('is also where admins land from /dashboard', function () {
        $this->actingAs(User::factory()->admin()->create())
            ->get('/dashboard')
            ->assertRedirect(route('admin.dashboard'));
    });
});
