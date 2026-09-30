<?php

use App\Models\Service;
use App\Models\User;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
});

function documentService(array $overrides = []): array
{
    return array_merge([
        'type' => 'document',
        'name' => 'Short Colored',
        'paper_size' => 'Short',
        'color_mode' => 'color',
        'price' => '5.50',
        'is_active' => '1',
    ], $overrides);
}

test('only admins can manage the price list', function (string $state) {
    $actor = User::factory()->{$state}()->create();
    $service = Service::factory()->create();

    $this->actingAs($actor)->get(route('admin.services.index'))->assertForbidden();
    $this->actingAs($actor)->post(route('admin.services.store'), documentService())->assertForbidden();
    $this->actingAs($actor)->put(route('admin.services.update', $service), documentService())->assertForbidden();
})->with(['student', 'staff']);

test('the price list shows document and photo services with prices', function () {
    Service::factory()->create(['name' => 'Long Black & White', 'price' => 2]);
    Service::factory()->photo()->create(['name' => 'Passport', 'price' => 60]);

    $this->actingAs($this->admin)
        ->get(route('admin.services.index'))
        ->assertOk()
        ->assertSee('Long Black &amp; White', false)
        ->assertSee('2.00')
        ->assertSee('Passport')
        ->assertSee('60.00');
});

test('admins add a document service priced per page', function () {
    $this->actingAs($this->admin)->get(route('admin.services.create'))->assertOk();

    $this->actingAs($this->admin)
        ->post(route('admin.services.store'), documentService())
        ->assertRedirect(route('admin.services.index'))
        ->assertSessionHas('success');

    $service = Service::sole();
    expect($service->type)->toBe('document')
        ->and($service->unit)->toBe('page')
        ->and($service->price)->toBe('5.50')
        ->and($service->paper_size)->toBe('Short')
        ->and($service->pieces_per_set)->toBeNull()
        ->and($service->is_active)->toBeTrue();
});

test('admins add a photo service priced per set', function () {
    $this->actingAs($this->admin)->post(route('admin.services.store'), [
        'type' => 'photo',
        'name' => '2x2',
        'color_mode' => 'color',
        'pieces_per_set' => 4,
        'price' => 40,
        'is_active' => '1',
    ])->assertSessionHasNoErrors();

    $service = Service::sole();
    expect($service->unit)->toBe('set')
        ->and($service->pieces_per_set)->toBe(4)
        ->and($service->paper_size)->toBeNull();
});

test('service validation', function (array $overrides, string $field) {
    $this->actingAs($this->admin)
        ->post(route('admin.services.store'), documentService($overrides))
        ->assertSessionHasErrors($field);

    expect(Service::count())->toBe(0);
})->with([
    'unknown type' => [['type' => 'poster'], 'type'],
    'name required' => [['name' => ''], 'name'],
    'document needs paper size' => [['paper_size' => ''], 'paper_size'],
    'bad color mode' => [['color_mode' => 'sepia'], 'color_mode'],
    'negative price' => [['price' => '-1'], 'price'],
    'too many decimals' => [['price' => '1.234'], 'price'],
    'photo needs pieces per set' => [['type' => 'photo', 'pieces_per_set' => ''], 'pieces_per_set'],
]);

test('service names are unique per type', function () {
    Service::factory()->create(['type' => 'document', 'name' => 'Short Colored']);

    $this->actingAs($this->admin)
        ->post(route('admin.services.store'), documentService())
        ->assertSessionHasErrors('name');
});

test('admins update prices and deactivate services', function () {
    $service = Service::factory()->create(['name' => 'Short Colored', 'price' => 5]);

    $this->actingAs($this->admin)->get(route('admin.services.edit', $service))->assertOk();

    $this->actingAs($this->admin)
        ->put(route('admin.services.update', $service), documentService(['price' => '7.25', 'is_active' => '0']))
        ->assertRedirect(route('admin.services.index'));

    $service->refresh();
    expect($service->price)->toBe('7.25')
        ->and($service->is_active)->toBeFalse();
});

test('updating keeps its own name without a uniqueness error', function () {
    $service = Service::factory()->create(['type' => 'document', 'name' => 'Short Colored']);

    $this->actingAs($this->admin)
        ->put(route('admin.services.update', $service), documentService())
        ->assertSessionHasNoErrors();
});

test('services cannot be deleted, only deactivated', function () {
    $service = Service::factory()->create();

    $this->actingAs($this->admin)->delete('/admin/services/'.$service->id)->assertMethodNotAllowed();
    expect($service->fresh())->not->toBeNull();
});
