<?php

namespace App\View\Components;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Component;
use Illuminate\View\View;

class Sidebar extends Component
{
    /**
     * Menu sections for the given user's role.
     * Each item: key, label, route name. Routes that do not exist yet render as disabled.
     *
     * @var array<string, list<array{key: string, label: string, route: string, url: ?string, active: bool}>>
     */
    public array $sections;

    public function __construct(public ?User $user = null)
    {
        $this->user ??= auth()->user();
        $this->sections = $this->sectionsFor($this->user?->role);
    }

    /**
     * @return array<string, list<array{key: string, label: string, route: string, url: ?string, active: bool}>>
     */
    private function sectionsFor(?Role $role): array
    {
        $sections = match ($role) {
            Role::Admin => [
                'Main' => $this->mainItems(),
                'Counter' => $this->counterItems(),
                'Administration' => $this->adminItems(),
            ],
            Role::Staff => [
                'Main' => $this->mainItems(),
                'Counter' => $this->counterItems(),
            ],
            Role::Student => [
                'Main' => $this->mainItems(),
                'Print' => $this->studentItems(),
            ],
            default => [],
        };

        if ($sections !== []) {
            $sections['Account'] = [['profile', 'Profile', 'profile.edit']];
        }

        return array_map(fn (array $items) => array_map($this->item(...), $items), $sections);
    }

    private function mainItems(): array
    {
        return [['dashboard', 'Dashboard', 'dashboard']];
    }

    private function studentItems(): array
    {
        return [
            ['new-print-job', 'New Print Job', 'student.jobs.create'],
            ['new-photo-job', 'New Photo Job', 'student.photos.create'],
            ['my-jobs', 'My Jobs', 'student.jobs.index'],
        ];
    }

    private function counterItems(): array
    {
        return [
            ['station', 'Station', 'staff.station'],
            ['queue', 'Queue', 'staff.queue'],
            ['claim', 'Claim', 'staff.claim'],
            ['walkin-sale', 'Walk-in Sale', 'staff.walkin.create'],
            ['submit-job', 'Submit Job', 'staff.jobs.create'],
            ['my-commission', 'My Commission', 'staff.commission'],
        ];
    }

    private function adminItems(): array
    {
        return [
            ['students', 'Students', 'admin.students.index'],
            ['staff', 'Staff', 'admin.staff.index'],
            ['price-list', 'Price List', 'admin.services.index'],
            ['settings', 'Settings', 'admin.settings.edit'],
            ['sales-report', 'Sales Report', 'admin.reports.sales'],
            ['payouts', 'Payouts', 'admin.payouts.index'],
            ['shift-history', 'Shift History', 'admin.shifts.index'],
            ['audit-log', 'Audit Log', 'admin.audit.index'],
            ['announcements', 'Announcements', 'admin.announcements.index'],
        ];
    }

    /**
     * @param  array{0: string, 1: string, 2: string}  $item
     * @return array{key: string, label: string, route: string, url: ?string, active: bool}
     */
    private function item(array $item): array
    {
        [$key, $label, $route] = $item;
        $exists = Route::has($route);

        return [
            'key' => $key,
            'label' => $label,
            'route' => $route,
            'url' => $exists ? route($route) : null,
            'active' => $exists && request()->routeIs($route),
        ];
    }

    public function render(): View
    {
        return view('components.sidebar');
    }
}
