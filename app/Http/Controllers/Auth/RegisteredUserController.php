<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\StudentRegisterRequest;
use App\Services\StudentRegistrationService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(): View
    {
        return view('auth.register');
    }

    /**
     * Register a student. The account stays pending (no session) until an admin approves it.
     */
    public function store(StudentRegisterRequest $request, StudentRegistrationService $registration): RedirectResponse
    {
        $student = $registration->register($request->safe()->only(['student_id', 'first_name', 'last_name', 'password']));

        event(new Registered($student));

        return redirect()->route('register.pending');
    }

    /**
     * Waiting page shown after signup.
     */
    public function pending(): View
    {
        return view('auth.registration-pending');
    }
}
