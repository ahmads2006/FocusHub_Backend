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
        $user = auth()->user();

        if ($user && ($user->hasRole('super_admin') || $user->id === $model->user_id)) {
            return; // super_admin يرى كل شيء
        }

        $userColumn = $model->getTable() === 'albums' || $model->getTable() === 'images'
            ? $model->getTable() . '.user_id'
            : null;

        if (!$userColumn) {
            return;
        }

        $hiddenCondition = fn ($q) => $q->whereHas('userStatus', fn ($s) => $s->where('is_shadow_hidden', true));

        if ($user) {
            $builder->where(function ($q) use ($user, $userColumn, $hiddenCondition) {
                $q->where($userColumn, $user->id)
                    ->orWhereDoesntHave('user', $hiddenCondition);
            });
        } else {
            $builder->whereDoesntHave('user', $hiddenCondition);
        }
    }
}
