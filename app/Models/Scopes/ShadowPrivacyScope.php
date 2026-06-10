<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Global Scope للحجب الشامل (Shadow Privacy).
 * يخفي محتوى المستخدمين ذوي is_shadow_hidden من الاستعلامات
 * إلا لـ: super_admin أو صاحب المحتوى نفسه.
 */
class ShadowPrivacyScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $table = $model->getTable();
        $userColumn = ($table === 'albums' || $table === 'images') ? $table . '.user_id' : null;

        if (!$userColumn) {
            return;
        }

        $hiddenCondition = fn ($q) => $q->whereHas('userStatus', fn ($s) => $s->where('is_shadow_hidden', true));
        $user = auth()->user();

        // status values for approved content
        $safeStatuses = ['approved', 'pending_review', 'under_review'];

        $builder->where(function ($query) use ($user, $userColumn, $hiddenCondition, $table, $safeStatuses) {
            // Logic for Images table (normalized status)
            if ($table === 'images') {
                $statusCheck = fn($q) => $q->where(function($sq) use ($safeStatuses) {
                    $sq->whereHas('moderation', fn($ssq) => $ssq->whereIn('status', $safeStatuses))
                       ->orWhereDoesntHave('moderation');
                });
            } elseif ($table === 'albums') {
                // Logic for Albums table (normalized status)
                $statusCheck = fn($q) => $q->where(function($sq) use ($safeStatuses) {
                    $sq->whereHas('settings', fn($ssq) => $ssq->whereIn('status', $safeStatuses))
                       ->orWhereDoesntHave('settings');
                });
            } else {
                // Default logic for other tables
                $statusCheck = fn($q) => $q->whereIn('status', $safeStatuses);
            }


            if ($user) {
                if ($user->role === 'super_admin') {
                    // Admin bypasses the profile shadow ban entirely but still respects standard feed status
                    $statusCheck($query);
                } else {
                    $query->where(function($sub) use ($user, $userColumn, $statusCheck) {
                        // Owner can see their own content regardless of profile shadow status
                        $sub->where($userColumn, $user->id);
                        $statusCheck($sub);
                    })->orWhere(function($sub) use ($hiddenCondition, $statusCheck) {
                        // Others see only safe content from non-hidden users
                        $statusCheck($sub);
                        $sub->whereDoesntHave('user', $hiddenCondition);
                    });
                }
            } else {
                // Guests see only safe content from non-hidden users
                $statusCheck($query);
                $query->whereDoesntHave('user', $hiddenCondition);
            }
        });
    }
}
