<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Spatie\Activitylog\Models\Activity;
use Illuminate\Support\Facades\Auth;

class ActivityController extends Controller
{
    /**
     * عرض سجل النشاطات الخاص بالمستخدم الحالي
     */
    public function index()
    {
        $activities = Activity::where('causer_id', Auth::id())
            ->latest()
            ->paginate(15);

        return view('activities.activities', compact('activities'));
    }
}
