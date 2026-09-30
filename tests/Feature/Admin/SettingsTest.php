<?php

use App\Models\Setting;
use App\Models\User;
use Database\Seeders\SettingSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->seed(SettingSeeder::class);
    $this->admin = User::factory()->admin()->create();
    Storage::fake('public');
});

function settingsData(array $overrides = []): array
{
    return array_merge([
        'commission_rate' => '12.5',
        'gcash_name' => 'MinSU Printing Room',
        'gcash_number' => '09171234567',
        'retention_days' => '14',
        'student_id_pattern' => '^[A-Z]{3}\d{4}-\d{5}$',
    ], $overrides);
}

test('only admins can open settings', function (string $state) {
    $this->actingAs(User::factory()->{$state}()->create())->get(route('admin.settings.edit'))->assertForbidden();
    $this->actingAs(User::factory()->{$state}()->create())->put(route('admin.settings.update'), settingsData())->assertForbidden();
})->with(['student', 'staff']);

test('the settings page shows current values', function () {
    $this->actingAs($this->admin)
        ->get(route('admin.settings.edit'))
        ->assertOk()
        ->assertSee('name="commission_rate"', false)
        ->assertSee('value="7"', false)
        ->assertSee('^[A-Z]{3}\d{4}-\d{5}$', false);
});

test('admins update every setting', function () {
    $this->actingAs($this->admin)
        ->put(route('admin.settings.update'), settingsData())
        ->assertRedirect(route('admin.settings.edit'))
        ->assertSessionHas('success');

    expect(Setting::read('commission_rate'))->toBe('12.5')
        ->and(Setting::read('gcash_name'))->toBe('MinSU Printing Room')
        ->and(Setting::read('gcash_number'))->toBe('09171234567')
        ->and(Setting::read('retention_days'))->toBe('14')
        ->and(Setting::read('student_id_pattern'))->toBe('^[A-Z]{3}\d{4}-\d{5}$');
});

test('updated settings take effect immediately through the cache', function () {
    expect(Setting::read('retention_days'))->toBe('7');

    $this->actingAs($this->admin)->put(route('admin.settings.update'), settingsData(['retention_days' => '30']));

    expect(Setting::read('retention_days'))->toBe('30');
});

test('the new student id pattern is used by signup', function () {
    $this->actingAs($this->admin)->put(route('admin.settings.update'), settingsData(['student_id_pattern' => '^\d{2}-\d{4}$']));
    auth()->logout();

    $this->post('/register', [
        'student_id' => '24-1234', 'first_name' => 'A', 'last_name' => 'B',
        'password' => 'password', 'password_confirmation' => 'password',
    ])->assertSessionHasNoErrors();
});

test('settings validation', function (array $overrides, string $field) {
    $this->actingAs($this->admin)
        ->put(route('admin.settings.update'), settingsData($overrides))
        ->assertSessionHasErrors($field);

    expect(Setting::read('commission_rate'))->toBe('0');
})->with([
    'rate above 100' => [['commission_rate' => '101'], 'commission_rate'],
    'negative rate' => [['commission_rate' => '-1'], 'commission_rate'],
    'rate required' => [['commission_rate' => ''], 'commission_rate'],
    'bad gcash number' => [['gcash_number' => '12345'], 'gcash_number'],
    'retention too low' => [['retention_days' => '0'], 'retention_days'],
    'retention not integer' => [['retention_days' => '1.5'], 'retention_days'],
    'invalid regex' => [['student_id_pattern' => '([unclosed'], 'student_id_pattern'],
    'pattern required' => [['student_id_pattern' => ''], 'student_id_pattern'],
]);

test('admins upload a GCash QR image and replacing it deletes the old one', function () {
    $this->actingAs($this->admin)->put(route('admin.settings.update'), settingsData([
        'gcash_qr' => UploadedFile::fake()->image('qr.png', 300, 300),
    ]))->assertSessionHasNoErrors();

    $first = Setting::read('gcash_qr_path');
    Storage::disk('public')->assertExists($first);
    expect($first)->toStartWith('gcash/');

    $this->actingAs($this->admin)->put(route('admin.settings.update'), settingsData([
        'gcash_qr' => UploadedFile::fake()->image('qr2.jpg', 300, 300),
    ]));

    $second = Setting::read('gcash_qr_path');
    expect($second)->not->toBe($first);
    Storage::disk('public')->assertExists($second);
    Storage::disk('public')->assertMissing($first);
});

test('saving without a new QR keeps the current one', function () {
    $this->actingAs($this->admin)->put(route('admin.settings.update'), settingsData([
        'gcash_qr' => UploadedFile::fake()->image('qr.png'),
    ]));
    $path = Setting::read('gcash_qr_path');

    $this->actingAs($this->admin)->put(route('admin.settings.update'), settingsData());

    expect(Setting::read('gcash_qr_path'))->toBe($path);
});

test('the GCash QR must be a small image', function () {
    $this->actingAs($this->admin)->put(route('admin.settings.update'), settingsData([
        'gcash_qr' => UploadedFile::fake()->create('qr.pdf', 100, 'application/pdf'),
    ]))->assertSessionHasErrors('gcash_qr');

    $this->actingAs($this->admin)->put(route('admin.settings.update'), settingsData([
        'gcash_qr' => UploadedFile::fake()->image('big.png')->size(3000),
    ]))->assertSessionHasErrors('gcash_qr');
});
