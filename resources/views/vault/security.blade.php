@extends('layouts.vault')

@section('title', __('vault.security') . ' — ' . __('vault.audit_center'))
@section('page-title', __('vault.security_center'))
@section('page-subtitle', __('vault.critical_ops_feed'))

@section('content')
{{-- ═══════════ Security Header ═══════════ --}}
<div class="flex flex-col md:flex-row md:items-center justify-between gap-6">
    <div class="flex items-center gap-3">
        <div class="w-12 h-12 rounded-xl bg-error/10 flex items-center justify-center">
            <span class="material-symbols-outlined text-error text-2xl filled">shield</span>
        </div>
        <div>
            <h2 class="font-headline font-bold text-2xl text-on-surface">{{ __('vault.security_event_stream') }}</h2>
            <p class="text-sm text-on-surface-variant mt-0.5">{{ __('vault.security_monitoring_subtitle') }}</p>
        </div>
    </div>
    <div class="flex items-center gap-3">
        <form method="GET" action="{{ route('vault.security') }}" class="flex items-center gap-3">
            <input name="date_from" value="{{ request('date_from') }}" type="date"
                   class="bg-surface-container-lowest border-none rounded-lg text-sm py-2 px-4 focus:ring-2 focus:ring-blue-500/20 transition-all">
            <button type="submit" class="px-4 py-2 bg-secondary-container text-on-secondary-container text-sm font-semibold rounded-lg hover:opacity-90 transition-all flex items-center gap-2">
                <span class="material-symbols-outlined text-sm">filter_list</span>
                {{ __('vault.filter') }}
            </button>
        </form>
        <span class="px-3 py-1.5 bg-error-container text-on-error-container text-[10px] font-bold rounded-lg flex items-center gap-1.5">
            <span class="w-1.5 h-1.5 bg-red-500 rounded-full pulse-dot"></span>
            {{ __('vault.live_monitoring') }}
        </span>
    </div>
</div>

{{-- ═══════════ Security Events Feed ═══════════ --}}
<div class="space-y-4">
    @forelse($alerts as $alert)
        @php
            $desc = strtolower($alert->description);
            $severity = match(true) {
                str_contains($desc, 'delet') || str_contains($desc, 'purge')  => 'critical',
                str_contains($desc, 'ban')                                      => 'high',
                str_contains($desc, 'role') || str_contains($desc, 'shadow')   => 'medium',
                default                                                          => 'low',
            };
            $severityConfig = match($severity) {
                'critical' => ['border' => app()->getLocale() === 'ar' ? 'border-r-4 border-red-500' : 'border-l-4 border-red-500', 'bg' => 'bg-red-50', 'icon_bg' => 'bg-red-100', 'icon_color' => 'text-red-600', 'badge' => 'bg-red-100 text-red-700', 'label' => __('vault.severity_critical')],
                'high'     => ['border' => app()->getLocale() === 'ar' ? 'border-r-4 border-orange-500' : 'border-l-4 border-orange-500', 'bg' => 'bg-orange-50', 'icon_bg' => 'bg-orange-100', 'icon_color' => 'text-orange-600', 'badge' => 'bg-orange-100 text-orange-700', 'label' => __('vault.severity_high')],
                'medium'   => ['border' => app()->getLocale() === 'ar' ? 'border-r-4 border-amber-400' : 'border-l-4 border-amber-400', 'bg' => 'bg-amber-50', 'icon_bg' => 'bg-amber-100', 'icon_color' => 'text-amber-600', 'badge' => 'bg-amber-100 text-amber-700', 'label' => __('vault.severity_medium')],
                'low'      => ['border' => app()->getLocale() === 'ar' ? 'border-r-4 border-blue-400' : 'border-l-4 border-blue-400', 'bg' => 'bg-blue-50', 'icon_bg' => 'bg-blue-100', 'icon_color' => 'text-blue-600', 'badge' => 'bg-blue-100 text-blue-700', 'label' => __('vault.severity_low')],
            };
        @endphp
        <div class="bg-surface-container-lowest rounded-xl shadow-sm overflow-hidden {{ $severityConfig['border'] }}">
            <div class="p-6 flex items-start gap-5">
                {{-- Icon --}}
                <div class="flex-shrink-0 w-12 h-12 rounded-xl {{ $severityConfig['icon_bg'] }} flex items-center justify-center">
                    <span class="material-symbols-outlined {{ $severityConfig['icon_color'] }} text-xl">
                        @if(str_contains($desc, 'delet'))
                            delete_forever
                        @elseif(str_contains($desc, 'ban'))
                            person_off
                        @elseif(str_contains($desc, 'role'))
                            admin_panel_settings
                        @elseif(str_contains($desc, 'shadow'))
                            visibility_off
                        @elseif(str_contains($desc, 'password') || str_contains($desc, 'credential'))
                            lock_reset
                        @else
                            security
                        @endif
                    </span>
                </div>

                {{-- Content --}}
                <div class="flex-1 min-w-0">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <h3 class="text-base font-bold text-on-surface">{{ $alert->description }}</h3>
                            <p class="text-sm text-on-surface-variant mt-1">
                                {{ __('vault.user') }}: 
                                <span class="font-semibold text-on-surface">{{ $alert->causer?->name ?? __('vault.system') }}</span>
                            </p>
                        </div>
                        <div class="flex items-center gap-3 flex-shrink-0">
                            <span class="px-2 py-0.5 {{ $severityConfig['badge'] }} text-[9px] font-bold rounded uppercase tracking-wider">
                                {{ $severityConfig['label'] }}
                            </span>
                            <span class="text-[10px] text-on-surface-variant font-bold whitespace-nowrap uppercase tracking-widest">
                                {{ $alert->created_at->diffForHumans() }}
                            </span>
                        </div>
                    </div>

                    {{-- Details --}}
                    <div class="mt-3 flex items-center gap-4 text-[10px] text-on-surface-variant uppercase font-bold tracking-widest">
                        <span class="flex items-center gap-1">
                            <span class="material-symbols-outlined text-xs">category</span>
                            {{ class_basename($alert->subject_type ?? __('vault.system')) }}
                        </span>
                        @if($alert->subject_id)
                            <span class="flex items-center gap-1">
                                <span class="material-symbols-outlined text-xs">tag</span>
                                {{ Str::limit($alert->subject_id, 10) }}
                            </span>
                        @endif
                        <span class="flex items-center gap-1">
                            <span class="material-symbols-outlined text-xs">schedule</span>
                            {{ $alert->created_at->format('M d, Y — H:i:s') }}
                        </span>
                    </div>

                    {{-- Properties --}}
                    @if($alert->properties && $alert->properties->count() > 0)
                        <div x-data="{ open: false }" class="mt-4">
                            <button @click="open = !open" class="text-[10px] font-bold text-on-surface uppercase tracking-widest hover:underline flex items-center gap-1">
                                <span class="material-symbols-outlined text-xs">data_object</span>
                                {{ __('vault.view_logs') }}
                                <span class="material-symbols-outlined text-xs" x-text="open ? 'expand_less' : 'expand_more'">expand_more</span>
                            </button>
                            <div x-show="open" x-cloak class="mt-2 bg-surface-container rounded-lg p-4 text-[11px] font-mono text-on-surface-variant max-h-[200px] overflow-y-auto custom-scrollbar">
                                <pre>{{ json_encode($alert->properties, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    @empty
        <div class="bg-surface-container-lowest rounded-xl shadow-sm p-16 text-center">
            <span class="material-symbols-outlined text-6xl text-emerald-300 block mb-4">verified_user</span>
            <p class="text-sm font-bold text-on-surface-variant">{{ __('vault.no_alerts') }}</p>
        </div>
    @endforelse
</div>

{{-- Pagination --}}
@if($alerts->hasPages())
    <div class="mt-8 flex items-center justify-between">
        <p class="text-[10px] text-on-surface-variant font-bold uppercase tracking-widest">
            {{ $alerts->firstItem() }} - {{ $alerts->lastItem() }} / {{ number_format($alerts->total()) }}
        </p>
        <div class="flex gap-2">
            @if($alerts->onFirstPage())
                <span class="w-8 h-8 flex items-center justify-center rounded-lg border border-outline-variant text-outline cursor-not-allowed">
                    <span class="material-symbols-outlined text-sm {{ app()->getLocale() === 'ar' ? 'rotate-180' : '' }}">chevron_left</span>
                </span>
            @else
                <a href="{{ $alerts->previousPageUrl() }}" class="w-8 h-8 flex items-center justify-center rounded-lg border border-outline-variant hover:bg-white transition-colors">
                    <span class="material-symbols-outlined text-sm {{ app()->getLocale() === 'ar' ? 'rotate-180' : '' }}">chevron_left</span>
                </a>
            @endif

            @foreach($alerts->getUrlRange(max(1, $alerts->currentPage() - 1), min($alerts->lastPage(), $alerts->currentPage() + 1)) as $page => $url)
                @if($page == $alerts->currentPage())
                    <span class="w-8 h-8 flex items-center justify-center rounded-lg bg-primary text-white text-[10px] font-bold">{{ $page }}</span>
                @else
                    <a href="{{ $url }}" class="w-8 h-8 flex items-center justify-center rounded-lg hover:bg-white transition-colors text-[10px] font-bold">{{ $page }}</a>
                @endif
            @endforeach

            @if($alerts->hasMorePages())
                <a href="{{ $alerts->nextPageUrl() }}" class="w-8 h-8 flex items-center justify-center rounded-lg border border-outline-variant hover:bg-white transition-colors">
                    <span class="material-symbols-outlined text-sm {{ app()->getLocale() === 'ar' ? 'rotate-180' : '' }}">chevron_right</span>
                </a>
            @else
                <span class="w-8 h-8 flex items-center justify-center rounded-lg border border-outline-variant text-outline cursor-not-allowed">
                    <span class="material-symbols-outlined text-sm {{ app()->getLocale() === 'ar' ? 'rotate-180' : '' }}">chevron_right</span>
                </span>
            @endif
        </div>
    </div>
@endif
@endsection

