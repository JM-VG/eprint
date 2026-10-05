<?php

use App\Enums\Role;
use App\Models\Service;
use App\Models\User;
use App\Rules\RecordExists;
use App\Rules\RecordUnique;
use Illuminate\Support\Facades\Validator;

function passesRule(mixed $value, object $rule, array $messages = []): bool|string
{
    $validator = Validator::make(['field' => $value], ['field' => [$rule]], $messages);

    return $validator->passes() ?: $validator->errors()->first('field');
}

it('checks that a record exists, with extra conditions', function () {
    $active = Service::factory()->create(['type' => 'document', 'is_active' => true]);
    $inactive = Service::factory()->create(['type' => 'document', 'is_active' => false]);

    expect(passesRule($active->id, RecordExists::of(Service::class)))->toBeTrue()
        ->and(passesRule((string) $active->id, RecordExists::of(Service::class)->where('is_active', true)))->toBeTrue()
        ->and(passesRule($inactive->id, RecordExists::of(Service::class)->where('is_active', true)))->toBe('The selected field is invalid.')
        ->and(passesRule(999, RecordExists::of('services')))->toBe('The selected field is invalid.')
        ->and(passesRule($active->id, RecordExists::of(Service::class)->where('type', 'photo')))->not->toBeTrue();
});

it('checks existence by another column with whereIn', function () {
    User::factory()->staff()->create(['username' => 'staff1']);
    User::factory()->create(['username' => 'student1', 'role' => Role::Student]);

    $rule = fn () => RecordExists::of(User::class, 'username')->whereIn('role', [Role::Staff->value, Role::Admin->value]);

    expect(passesRule('staff1', $rule()))->toBeTrue()
        ->and(passesRule('student1', $rule()))->not->toBeTrue();
});

it('checks uniqueness and can ignore the edited record', function () {
    $user = User::factory()->create(['username' => 'taken']);

    expect(passesRule('taken', RecordUnique::of(User::class, 'username')))->toBe('The field has already been taken.')
        ->and(passesRule('taken', RecordUnique::of(User::class, 'username')->ignore($user)))->toBeTrue()
        ->and(passesRule('free', RecordUnique::of(User::class, 'username')))->toBeTrue();
});

it('uses custom attribute.rule messages like Rule::unique did', function () {
    User::factory()->create(['username' => 'taken']);

    expect(passesRule('taken', RecordUnique::of(User::class, 'username'), ['field.unique' => 'Pick another name.']))
        ->toBe('Pick another name.');
});
