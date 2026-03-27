@extends('layouts.vault')

@section('title', __('vault.overview') . ' — ' . __('vault.audit_center'))
@section('page-title', __('vault.overview'))
@section('page-subtitle', __('vault.health_dashboard'))

@section('content')
{{-- ═══════════ Stats Cards ═══════════ --}}
<div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4">
    <div class="bg-surface-container-lowest rounded-xl p-5 shadow-sm hover:shadow-md transition-shadow">
        <div class="flex items-center justify-between mb-3">
            <span class="material-symbols-outlined text-blue-500 text-xl">group</span>
            <span class="text-[9px] font-bold text-on-surface-variant uppercase tracking-widest">{{ __('vault.total_users') }}</span>
        </div>
        <h3 class="text-2xl font-extrabold font-headline text-on-surface">{{ number_format($stats['total_users']) }}</h3>
        <p class="text-[10px] text-on-surface-variant mt-1">{{ __('vault.registered_accounts') }}</p>
    </div>

    <div class="bg-surface-container-lowest rounded-xl p-5 shadow-sm hover:shadow-md transition-shadow">
        <div class="flex items-center justify-between mb-3">
            <span class="material-symbols-outlined text-indigo-500 text-xl">photo_library</span>
            <span class="text-[9px] font-bold text-on-surface-variant uppercase tracking-widest">{{ __('vault.total_images') }}</span>
        </div>
        <h3 class="text-2xl font-extrabold font-headline text-on-surface">{{ number_format($stats['total_images']) }}</h3>
        <p class="text-[10px] text-on-surface-variant mt-1">{{ __('vault.total_assets') }}</p>
    </div>

    <div class="bg-surface-container-lowest rounded-xl p-5 shadow-sm hover:shadow-md transition-shadow">
        <div class="flex items-center justify-between mb-3">
            <span class="material-symbols-outlined text-emerald-500 text-xl">folder</span>
            <span class="text-[9px] font-bold text-on-surface-variant uppercase tracking-widest">{{ __('vault.total_albums') }}</span>
        </div>
        <h3 class="text-2xl font-extrabold font-headline text-on-surface">{{ number_format($stats['total_albums']) }}</h3>
        <p class="text-[10px] text-on-surface-variant mt-1">{{ __('vault.active_albums') }}</p>
    </div>

    <div class="bg-surface-container-lowest rounded-xl p-5 shadow-sm hover:shadow-md transition-shadow">
        <div class="flex items-center justify-between mb-3">
            <span class="material-symbols-outlined text-red-500 text-xl">block</span>
            <span class="text-[9px] font-bold text-on-surface-variant uppercase tracking-widest">{{ __('vault.banned') }}</span>
        </div>
        <h3 class="text-2xl font-extrabold font-headline text-on-surface">{{ number_format($stats['banned_users']) }}</h3>
        <p class="text-[10px] text-on-surface-variant mt-1">{{ __('vault.blocked_accounts') }}</p>
    </div>

    <div class="bg-surface-container-lowest rounded-xl p-5 shadow-sm hover:shadow-md transition-shadow">
        <div class="flex items-center justify-between mb-3">
            <span class="material-symbols-outlined text-amber-500 text-xl">receipt_long</span>
            <span class="text-[9px] font-bold text-on-surface-variant uppercase tracking-widest">{{ __('vault.total_operations') }}</span>
        </div>
        <h3 class="text-2xl font-extrabold font-headline text-on-surface">{{ number_format($stats['total_operations']) }}</h3>
        <p class="text-[10px] text-on-surface-variant mt-1">{{ __('vault.total_logged') }}</p>
    </div>

    <div class="bg-surface-container-lowest rounded-xl p-5 shadow-sm hover:shadow-md transition-shadow">
        <div class="flex items-center justify-between mb-3">
            <span class="material-symbols-outlined text-cyan-500 text-xl">today</span>
            <span class="text-[9px] font-bold text-on-surface-variant uppercase tracking-widest">{{ __('vault.today') }}</span>
        </div>
        <h3 class="text-2xl font-extrabold font-headline text-on-surface">{{ number_format($stats['today_operations']) }}</h3>
        <p class="text-[10px] text-on-surface-variant mt-1">{{ __('vault.operations_today') }}</p>
    </div>
</div>

{{-- ═══════════ Two-Column Layout ═══════════ --}}
<div class="grid grid-cols-12 gap-6">

    {{-- Tracking Checklist --}}
    <section class="col-span-12 lg:col-span-4 bg-surface-container-lowest rounded-xl p-6 shadow-sm">
        <div class="flex justify-between items-center mb-6">
            <h2 class="font-headline font-bold text-lg text-on-surface">{{ __('vault.tracking_checklist') }}</h2>
            <button class="text-primary-container hover:text-on-primary-container transition-colors">
                <span class="material-symbols-outlined">more_vert</span>
            </button>
        </div>
        <div class="space-y-4">
            <div class="flex items-start gap-4 p-3 rounded-lg hover:bg-surface-container transition-colors group">
                <input checked class="mt-1 rounded text-blue-600 border-outline-variant focus:ring-blue-500/20" type="checkbox" readonly/>
                <div class="flex-1">
                    <p class="text-sm font-semibold">Album Creation Log</p>
                    <p class="text-xs text-on-surface-variant">Verify automated tagging on new uploads</p>
                </div>
            </div>
            <div class="flex items-start gap-4 p-3 rounded-lg hover:bg-surface-container transition-colors group">
                <input class="mt-1 rounded text-blue-600 border-outline-variant focus:ring-blue-500/20" type="checkbox" readonly/>
                <div class="flex-1">
                    <p class="text-sm font-semibold">EXIF Metadata Scrubbing</p>
                    <p class="text-xs text-on-surface-variant">Check for GPS coordinate removal</p>
                </div>
            </div>
            <div class="flex items-start gap-4 p-3 rounded-lg hover:bg-surface-container transition-colors group">
                <input checked class="mt-1 rounded text-blue-600 border-outline-variant focus:ring-blue-500/20" type="checkbox" readonly/>
                <div class="flex-1">
                    <p class="text-sm font-semibold">User Permission Audit</p>
                    <p class="text-xs text-on-surface-variant">Review elevated access logs for 48h</p>
                </div>
            </div>
        </div>
        <a href="{{ route('vault.operations') }}" class="w-full mt-6 py-2 border border-dashed border-outline-variant rounded-lg text-[10px] font-bold text-on-surface-variant hover:bg-surface-container-low transition-colors block text-center uppercase tracking-widest">
            {{ __('vault.view_full_ledger') }}
        </a>
    </section>

    {{-- Critical Security Alerts --}}
    <section class="col-span-12 lg:col-span-8 relative overflow-hidden bg-surface-container-lowest rounded-xl p-6 shadow-sm flex flex-col">
        <div class="flex items-center justify-between mb-6">
            <div class="flex items-center gap-2">
                <span class="material-symbols-outlined text-error filled">warning</span>
                <h2 class="font-headline font-bold text-lg text-on-surface">{{ __('vault.critical_security_alerts') }}</h2>
            </div>
            <span class="px-2 py-1 bg-error-container text-on-error-container text-[10px] font-bold rounded flex items-center gap-1">
                <span class="w-1.5 h-1.5 bg-red-500 rounded-full pulse-dot"></span>
                {{ __('vault.live_feed') }}
            </span>
        </div>
        <div class="space-y-3 custom-scrollbar overflow-y-auto max-h-[300px] pr-2">
            @forelse($criticalAlerts as $alert)
                <div class="flex items-center gap-4 p-4 {{ $loop->first ? 'bg-error-container/10 border-l-4 border-error rounded-r-lg' : 'bg-surface-container rounded-lg' }}">
                    <div class="flex-shrink-0 w-10 h-10 rounded-full {{ $loop->first ? 'bg-error/10' : 'bg-on-surface/5' }} flex items-center justify-center">
                        <span class="material-symbols-outlined {{ $loop->first ? 'text-error' : 'text-on-surface-variant' }}">
                            @if(str_contains(strtolower($alert->description), 'delet'))
                                delete_forever
                            @elseif(str_contains(strtolower($alert->description), 'ban'))
                                person_off
                            @elseif(str_contains(strtolower($alert->description), 'role'))
                                admin_panel_settings
                            @else
                                lock_reset
                            @endif
                        </span>
                    </div>
                    <div class="flex-1">
                        <div class="flex justify-between items-start">
                            <h3 class="text-sm font-bold text-on-surface">{{ Str::limit($alert->description, 40) }}</h3>
                            <span class="text-[10px] text-on-surface-variant font-medium">{{ $alert->created_at->diffForHumans(null, true) }}</span>
                        </div>
                        <p class="text-xs text-on-surface-variant">
                            {{ __('vault.user') }}: <span class="font-semibold text-on-surface">{{ $alert->causer?->name ?? __('vault.system') }}</span>
                            · {{ class_basename($alert->subject_type ?? __('vault.system')) }}
                        </p>
                    </div>
                    <a href="{{ route('vault.operations') }}" class="px-3 py-1 bg-on-surface text-white text-[10px] font-bold rounded-lg hover:opacity-90 transition-opacity uppercase tracking-widest">{{ __('vault.audit_log') }}</a>
                </div>
            @empty
                <div class="p-12 text-center text-on-surface-variant text-sm">
                    <span class="material-symbols-outlined text-4xl text-outline mb-3 block">verified_user</span>
                    {{ __('vault.no_alerts') }}
                </div>
            @endforelse
        </div>
    </section>
</div>

{{-- ═══════════ Recent Activity Stream ═══════════ --}}
<section class="bg-surface-container-lowest rounded-xl shadow-sm overflow-hidden">
    <div class="p-8 border-b border-outline-variant/15">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="font-headline font-bold text-2xl text-on-surface">{{ __('vault.recent_activity_stream') }}</h2>
                <p class="text-sm text-on-surface-variant mt-1">{{ __('vault.latest_ops_across_modules') }}</p>
            </div>
            <a href="{{ route('vault.operations') }}" class="px-6 py-2 bg-primary text-on-primary text-sm font-bold rounded-lg hover:opacity-90 transition-all flex items-center gap-2 shadow-lg shadow-primary/10">
                <span class="material-symbols-outlined text-sm">open_in_new</span>
                {{ __('vault.full_ledger') }}
            </a>
        </div>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="bg-surface-container-low">
                    <th class="px-8 py-4 text-[10px] font-bold uppercase tracking-widest text-on-surface-variant">{{ __('vault.operation') }}</th>
                    <th class="px-8 py-4 text-[10px] font-bold uppercase tracking-widest text-on-surface-variant">{{ __('vault.user') }}</th>
                    <th class="px-8 py-4 text-[10px] font-bold uppercase tracking-widest text-on-surface-variant">{{ __('vault.model') }}</th>
                    <th class="px-8 py-4 text-[10px] font-bold uppercase tracking-widest text-on-surface-variant">{{ __('vault.old_new') }}</th>
                    <th class="px-8 py-4 text-[10px] font-bold uppercase tracking-widest text-on-surface-variant">{{ __('vault.timestamp') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-outline-variant/10">
                @forelse($recentActivities as $a)
                    <tr class="hover:bg-surface-container-high transition-colors group">
                        <td class="px-8 py-5">
                            <span class="px-3 py-1 text-[10px] font-bold rounded-full
                                @if(str_contains(strtolower($a->description), 'created'))
                                    bg-emerald-100 text-emerald-700
                                @elseif(str_contains(strtolower($a->description), 'updated'))
                                    bg-blue-100 text-blue-700
                                @elseif(str_contains(strtolower($a->description), 'deleted'))
                                    bg-red-100 text-red-700
                                @else
                                    bg-secondary-container text-on-secondary-container
                                @endif
                             uppercase tracking-tighter">{{ Str::limit($a->description, 25) }}</span>
                        </td>
                        <td class="px-8 py-5">
                            <div class="flex items-center gap-2">
                                <div class="w-6 h-6 rounded-full bg-blue-100 flex items-center justify-center text-[10px] font-bold text-blue-600">
                                    {{ strtoupper(substr($a->causer?->name ?? 'S', 0, 2)) }}
                                </div>
                                <p class="text-sm font-medium">{{ $a->causer?->name ?? __('vault.system') }}</p>
                            </div>
                        </td>
                        <td class="px-8 py-5">
                            <span class="px-2 py-0.5 text-[10px] font-bold rounded border border-outline-variant/30 text-on-surface-variant uppercase">
                                {{ class_basename($a->subject_type ?? __('vault.system')) }}
                            </span>
                        </td>
                        <td class="px-8 py-5">
                            @if($a->properties && ($a->properties->has('old') || $a->properties->has('attributes')))
                                <div class="text-[10px] text-on-surface-variant font-mono max-w-[200px] truncate" title="{{ json_encode($a->properties) }}">
                                    @if($a->properties->has('old'))
                                        <span class="text-red-400">{{ Str::limit(json_encode($a->properties['old']), 40) }}</span>
                                    @endif
                                    @if($a->properties->has('attributes'))
                                        <span class="text-emerald-500"> → {{ Str::limit(json_encode($a->properties['attributes']), 40) }}</span>
                                    @endif
                                </div>
                            @else
                                <span class="text-[10px] text-outline">—</span>
                            @endif
                        </td>
                        <td class="px-8 py-5">
                            <p class="text-sm text-on-surface leading-none">{{ $a->created_at->format('M d, Y') }}</p>
                            <p class="text-[10px] text-on-surface-variant mt-1">{{ $a->created_at->format('H:i:s') }} GMT</p>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-8 py-20 text-center text-on-surface-variant text-sm">
                            <span class="material-symbols-outlined text-4xl text-outline block mb-2">inbox</span>
                            {{ __('vault.no_activity') }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>
@endsection
