<?php

namespace App\Auth;

use App\Models\User;

/**
 * The authenticated user as loaded by the "rtdb" user provider: a User model
 * built straight from its Realtime Database record.
 *
 * Being a User, it keeps the enum/date casts, relations, notifications and
 * policies; it reports the User morph class so notifications and other
 * polymorphic records point at the same type.
 */
class RealtimeUser extends User
{
    protected $table = 'users';

    /**
     * @param  array<string, mixed>  $attributes  a stored user record
     */
    public function __construct(array $attributes = [])
    {
        parent::__construct();

        if ($attributes !== []) {
            $this->setRawAttributes($attributes, true);
            $this->exists = true;
        }
    }

    public function getForeignKey()
    {
        return 'user_id';
    }

    public function getMorphClass()
    {
        return User::class;
    }

    /**
     * The raw stored attributes.
     *
     * @return array<string, mixed>
     */
    public function attributes(): array
    {
        return $this->getAttributes();
    }
}
