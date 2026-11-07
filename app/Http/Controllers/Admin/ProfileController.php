<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Enquiry;
use App\Models\Property;
use App\Support\Media;
use App\Support\SiteSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class ProfileController extends Controller
{
    public function edit(Request $request)
    {
        return view('admin.profile.edit', [
            'user'  => $request->user(),
            'stats' => [
                'listings'  => Property::count(),
                'sold'      => Property::sold()->count(),
                'enquiries' => Enquiry::count(),
                'joined'    => $request->user()->created_at,
            ],
        ]);
    }

    public function update(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'name'  => ['required', 'string', 'max:120'],
            'email' => ['required', 'email:rfc', 'max:180', Rule::unique('users')->ignore($user->id)],
        ]);

        $user->update($data);

        return back()->with('status', 'Profile updated.');
    }

    public function password(Request $request)
    {
        $request->validate([
            'current_password' => ['required', 'current_password'],
            'password'         => ['required', 'confirmed', Password::min(8)],
        ], [
            'current_password.current_password' => 'That is not your current password.',
        ]);

        $request->user()->update(['password' => Hash::make($request->input('password'))]);

        // Keep this session signed in, sign out everywhere else.
        Auth::logoutOtherDevices($request->input('password'));
        $request->session()->regenerate();

        return back()->with('status', 'Password changed. Any other signed-in sessions have been ended.');
    }

    /**
     * The agent portrait is public-facing branding, but it is a picture of the
     * person signed in - so it is edited here rather than buried in settings.
     */
    public function photo(Request $request, SiteSettings $settings)
    {
        $request->validate([
            'photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        $values = [];

        foreach (['photo', 'avatar'] as $key) {
            $current = config('agent.'.$key);

            if (Media::isUpload($current)) {
                Storage::disk('public')->delete($current);
            }

            $values[$key] = $request->file('photo')->store('branding', 'public');
        }

        $settings->put($values);

        return back()->with('status', 'Portrait updated. It now appears on the home page, about page and every listing.');
    }
}
