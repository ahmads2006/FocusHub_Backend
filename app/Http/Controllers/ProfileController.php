<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        return Redirect::route('profile.edit')->with('status', 'profile-updated');
    }

    /**
     * Update the user's security settings.
     */
    public function updateSecurity(Request $request): RedirectResponse
    {
        $user = $request->user();
        $user->stay_logged_in = $request->has('stay_logged_in');
        $user->save();

        return Redirect::route('profile.edit')->with('status', 'security-updated');
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validateWithBag('userDeletion', [
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }

    /**
     * Provide stay logged info for frontend info button.
     */
    public function stayLoggedInfo(Request $request)
    {
        $message = 'إبقاء الجلسة نشطة دائماً يعني أن حسابك سيبقى مسجلاً دون تسجيل خروج تلقائي. يمكنك إلغاء ذلك في أي وقت بتعطيل هذا الخيار.';
        return response()->json(['message' => $message]);
    }
}
