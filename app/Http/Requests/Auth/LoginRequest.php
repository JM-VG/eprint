<?php

namespace App\Http\Requests\Auth;

use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    /**
     * Failed attempts allowed per student ID / username and IP.
     */
    public const MAX_ATTEMPTS = 5;

    /**
     * Lockout window in seconds (15 minutes).
     */
    public const DECAY_SECONDS = 900;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('login'))) {
            $this->merge(['login' => trim($this->input('login'))]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'login' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['login' => 'Student ID or Username'];
    }

    /**
     * Authenticate by student ID (students) or username (staff, admin).
     * Only approved accounts get a session; the status is revealed only after a correct password.
     *
     * @throws ValidationException
     */
    public function authenticate(): User
    {
        $this->ensureIsNotRateLimited();

        $user = $this->findUser();

        if (! $user || ! Hash::check($this->string('password'), $user->password)) {
            RateLimiter::hit($this->throttleKey(), self::DECAY_SECONDS);

            throw ValidationException::withMessages([
                'login' => trans('auth.failed'),
            ]);
        }

        RateLimiter::clear($this->throttleKey());

        if ($user->status !== UserStatus::Approved) {
            throw ValidationException::withMessages([
                'login' => $user->status->loginMessage($user->rejection_reason),
            ]);
        }

        Auth::login($user, $this->boolean('remember'));

        return $user;
    }

    private function findUser(): ?User
    {
        $login = $this->string('login')->toString();

        return User::where('student_id', strtoupper($login))->first()
            ?? User::where('username', $login)->first();
    }

    /**
     * Ensure the login request is not rate limited.
     *
     * @throws ValidationException
     */
    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), self::MAX_ATTEMPTS)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'login' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    /**
     * Get the rate limiting throttle key for the request.
     */
    public function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->string('login')).'|'.$this->ip());
    }
}
