<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Image;
use Spatie\Activitylog\Models\Activity;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        
        $stats = [
            'total_images' => $user->images()->count(),
            'total_albums' => $user->albums()->count(),
            'recent_activities' => Activity::where('causer_id', $user->id)->latest()->take(5)->get(),
        ];

        return view('dashboard', compact('stats'));
    }
}
