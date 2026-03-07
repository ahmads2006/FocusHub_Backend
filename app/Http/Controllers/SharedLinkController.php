<?php

namespace App\Http\Controllers;

use App\Models\Album;
use App\Models\Image;
use App\Models\SharedLink;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class SharedLinkController extends Controller
{
    public function show(Request $request, $token)
    {
        // Link is already validated and retrieved by middleware
        $link = $request->attributes->get('shared_link');
        $shareable = $link->shareable;

        if ($shareable instanceof Album) {
            return view('shared_links.album', [
                'album' => $shareable,
                'link' => $link
            ]);
        }

        if ($shareable instanceof Image) {
            return view('shared_links.photo', [
                'photo' => $shareable,
                'link' => $link
            ]);
        }

        abort(404, 'Shareable content type is not supported.');
    }

    public function verifyPassword(Request $request, $token)
    {
        $link = SharedLink::where('token', $token)->firstOrFail();
        
        $request->validate([
            'password' => 'required|string',
        ]);

        if (Hash::check($request->password, $link->password)) {
            $request->session()->put("link_auth_{$link->id}", true);
            return redirect()->route('shared.link.show', $token);
        }

        return back()->withErrors(['password' => 'كلمة المرور غير صحيحة.']);
    }
}
