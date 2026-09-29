<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Illuminate\View\View;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): View
    {
        return view('profile.edit', [
            'user' => $request->user(),
        ]);
    }

    /**
     * Update the user's names.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $request->user()->forceFill([
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'name' => trim("{$data['first_name']} {$data['last_name']}"),
        ])->save();

        return Redirect::route('profile.edit')->with('status', 'profile-updated');
    }
}
