@extends('layouts.vault')

@section('title', __('vault.operations') . ' — ' . __('vault.audit_center'))
@section('page-title', __('vault.operations') . ' & ' . __('vault.users'))
@section('page-subtitle', __('vault.ops_ledger_subtitle'))

@section('content')
{{-- ═══════════ Top Section: Asymmetric Layout ═══════════ --}}
<div class="grid grid-cols-12 gap-6">

    {{-- ─── Tracking Checklist ─── --}}
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
    </section>

    {{-- ─── Critical Security Alerts (Live Feed) ─── --}}
    <section class="col-span-12 lg:col-span-8 relative overflow-hidden bg-surface-container-lowest rounded-xl p-6 shadow-sm flex flex-col">
        <div class="flex items-center justify-between mb-6">
            <div class="flex items-center gap-2">
                <span class="material-symbols-outlined text-error filled">warning</span>
                <h2 class="font-headline font-bold text-lg text-on-surface">{{ __('vault.critical_security_alerts') }}</h2>
            </div>
            <span class="px-2 py-1 bg-error-container text-on-error-container text-[10px] font-bold rounded flex items-center gap-1.5">
                <span class="w-1.5 h-1.5 bg-red-500 rounded-full pulse-dot"></span>
                {{ __('vault.live_feed') }}
            </span>
        </div>
        <div class="space-y-3 custom-scrollbar overflow-y-auto max-h-[300px] pr-2">
            @forelse($criticalAlerts as $alert)
                <div class="flex items-center gap-4 p-4 
                    {{ $loop->first ? 'bg-error-container/10 border-l-4 border-error rounded-r-lg' : 'bg-surface-container rounded-lg' }}">
                    <div class="flex-shrink-0 w-10 h-10 rounded-full {{ $loop->first ? 'bg-error/10' : 'bg-on-surface/5' }} flex items-center justify-center">
                        <span class="material-symbols-outlined {{ $loop->first ? 'text-error' : 'text-on-surface-variant' }}">
                            @if(str_contains(strtolower($alert->description), 'delet'))
                                delete_forever
                            @elseif(str_contains(strtolower($alert->description), 'ban'))
                                person_off
                            @elseif(str_contains(strtolower($alert->description), 'role') || str_contains(strtolower($alert->description), 'credential'))
                                lock_reset
                            @elseif(str_contains(strtolower($alert->description), 'remov'))
                                remove_circle
                            @else
                                shield
                            @endif
                        </span>
                    </div>
                    <div class="flex-1">
                        <div class="flex justify-between items-start">
                            <h3 class="text-sm font-bold text-on-surface">{{ Str::limit($alert->description, 45) }}</h3>
                            <span class="text-[10px] text-on-surface-variant font-medium whitespace-nowrap ml-3">{{ $alert->created_at->diffForHumans(null, true) }}</span>
                        </div>
                        <p class="text-xs text-on-surface-variant mt-0.5">
                            {{ __('vault.user') }}: <span class="font-semibold text-on-surface">{{ $alert->causer?->name ?? __('vault.system') }}</span>
                            @if($alert->subject)
                                · {{ class_basename($alert->subject_type) }} #{{ Str::limit($alert->subject_id, 8) }}
                            @endif
                        </p>
                    </div>
                    <a href="{{ route('vault.security') }}" class="px-3 py-1 bg-on-surface text-white text-[10px] font-bold rounded-lg hover:opacity-90 transition-opacity whitespace-nowrap uppercase tracking-widest">
                        {{ __('vault.audit_log') }}
                    </a>
                </div>
            @empty
                <div class="flex items-center gap-4 p-4 bg-surface-container rounded-lg">
                    <div class="flex-shrink-0 w-10 h-10 rounded-full bg-on-surface/5 flex items-center justify-center">
                        <span class="material-symbols-outlined text-on-surface-variant">verified_user</span>
                    </div>
                    <div class="flex-1">
                        <h3 class="text-sm font-bold text-on-surface">{{ __('vault.no_alerts') }}</h3>
                    </div>
                </div>
            @endforelse
        </div>
    </section>
</div>

{{-- ═══════════ Main Section: Operations Ledger ═══════════ --}}
<section class="bg-surface-container-lowest rounded-xl shadow-sm overflow-hidden">
    {{-- Header + Filters --}}
    <div class="p-8 border-b border-outline-variant/15">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-6">
            <div>
                <h2 class="font-headline font-bold text-2xl text-on-surface">{{ __('vault.recent_activity_stream') }}</h2>
                <p class="text-sm text-on-surface-variant mt-1">{{ __('vault.latest_ops_across_modules') }}</p>
            </div>
            <div class="flex items-center gap-3">
                <button onclick="document.getElementById('filters-panel').classList.toggle('hidden')" 
                        class="px-4 py-2 bg-secondary-container text-on-secondary-container text-sm font-semibold rounded-lg hover:opacity-90 transition-all flex items-center gap-2">
                    <span class="material-symbols-outlined text-sm">filter_list</span>
                    {{ __('vault.filter') }}
                </button>
                <a href="{{ route('vault.operations') }}" class="px-6 py-2 bg-primary text-on-primary text-sm font-bold rounded-lg hover:opacity-90 transition-all flex items-center gap-2 shadow-lg shadow-primary/10">
                    <span class="material-symbols-outlined text-sm">refresh</span>
                    {{ __('vault.reset') }}
                </a>
            </div>
        </div>

        {{-- Filters Bar --}}
        <div id="filters-panel" class="{{ request()->hasAny(['operation', 'username', 'date_from', 'date_to']) ? '' : 'hidden' }}">
            <form method="GET" action="{{ route('vault.operations') }}" class="grid grid-cols-1 md:grid-cols-4 gap-4 mt-8">
                <div class="space-y-1">
                    <label class="text-[10px] font-bold uppercase tracking-widest text-on-surface-variant">{{ __('vault.operation') }}</label>
                    <select name="operation" class="w-full bg-surface border-none rounded-lg text-sm text-on-surface py-2 focus:ring-2 focus:ring-blue-500/20 transition-all">
                        <option value="">{{ __('vault.all_operations') }}</option>
                        @foreach($operationTypes as $type)
                            <option value="{{ $type }}" {{ request('operation') == $type ? 'selected' : '' }}>{{ ucfirst($type) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="space-y-1">
                    <label class="text-[10px] font-bold uppercase tracking-widest text-on-surface-variant">{{ __('vault.user') }}</label>
                    <input name="username" value="{{ request('username') }}"
                           class="w-full bg-surface border-none rounded-lg text-sm text-on-surface py-2 focus:ring-2 focus:ring-blue-500/20 transition-all" 
                           placeholder="{{ __('vault.search_placeholder') }}" type="text"/>
                </div>
                <div class="space-y-1">
                    <label class="text-[10px] font-bold uppercase tracking-widest text-on-surface-variant">{{ __('vault.timestamp') }}</label>
                    <div class="flex gap-2">
                        <input name="date_from" value="{{ request('date_from') }}"
                               class="w-full bg-surface border-none rounded-lg text-sm text-on-surface py-2 focus:ring-2 focus:ring-blue-500/20 transition-all" 
                               type="date"/>
                        <input name="date_to" value="{{ request('date_to') }}"
                               class="w-full bg-surface border-none rounded-lg text-sm text-on-surface py-2 focus:ring-2 focus:ring-blue-500/20 transition-all" 
                               type="date"/>
                    </div>
                </div>
                <div class="flex items-end">
                    <button type="submit" class="w-full py-2 bg-on-surface-variant/5 hover:bg-on-surface-variant/10 text-on-surface-variant text-sm font-bold rounded-lg transition-colors uppercase tracking-widest">
                        {{ __('vault.filter') }}
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- Table --}}
    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="bg-surface-container-low">
                    <th class="px-8 py-4 text-[10px] font-bold uppercase tracking-widest text-on-surface-variant">{{ __('vault.model') }}</th>
                    <th class="px-8 py-4 text-[10px] font-bold uppercase tracking-widest text-on-surface-variant">{{ __('vault.operation') }}</th>
                    <th class="px-8 py-4 text-[10px] font-bold uppercase tracking-widest text-on-surface-variant">{{ __('vault.user') }}</th>
                    <th class="px-8 py-4 text-[10px] font-bold uppercase tracking-widest text-on-surface-variant">{{ __('vault.old_new') }}</th>
                    <th class="px-8 py-4 text-[10px] font-bold uppercase tracking-widest text-on-surface-variant">{{ __('vault.timestamp') }}</th>
                    <th class="px-8 py-4 text-[10px] font-bold uppercase tracking-widest text-on-surface-variant text-right">{{ __('vault.actions') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-outline-variant/10">
                @forelse($activities as $a)
                    <tr class="hover:bg-surface-container-high transition-colors group">
                        {{-- Asset --}}
                        <td class="px-8 py-5">
                            <div class="flex items-center gap-4">
                                <div class="w-10 h-10 rounded-lg bg-on-surface/5 flex items-center justify-center flex-shrink-0">
                                    <span class="material-symbols-outlined text-on-surface-variant text-sm">
                                        @php
                                            $subjectBase = class_basename($a->subject_type ?? '');
                                        @endphp
                                        @if($subjectBase === 'Image')
                                            image
                                        @elseif($subjectBase === 'Album')
                                            photo_album
                                        @elseif($subjectBase === 'User')
                                            person
                                        @elseif($subjectBase === 'SharedLink')
                                            link
                                        @else
                                            description
                                        @endif
                                    </span>
                                </div>
                                <div>
                                    <p class="text-sm font-bold text-on-surface leading-tight">
                                        @if($a->subject && method_exists($a->subject, 'getAttribute'))
                                            {{ Str::limit($a->subject->getAttribute('title') ?? $a->subject->getAttribute('name') ?? $a->subject->getAttribute('email') ?? 'ID: ' . Str::limit($a->subject_id, 8), 30) }}
                                        @else
                                            {{ $subjectBase ?: __('vault.system') }} #{{ Str::limit($a->subject_id ?? '—', 8) }}
                                        @endif
                                    </p>
                                    <p class="text-[10px] text-on-surface-variant uppercase tracking-tighter">{{ $subjectBase ?: __('vault.system') }}</p>
                                </div>
                            </div>
                        </td>

                        {{-- Operation Badge --}}
                        <td class="px-8 py-5">
                            @php
                                $desc = strtolower($a->description);
                                $badgeClass = match(true) {
                                    str_contains($desc, 'created') => 'bg-emerald-100 text-emerald-700',
                                    str_contains($desc, 'updated') => 'bg-secondary-container text-on-secondary-container',
                                    str_contains($desc, 'deleted') => 'bg-red-100 text-red-700',
                                    str_contains($desc, 'uploaded') || str_contains($desc, 'upload') => 'bg-surface-container-highest text-on-surface',
                                    str_contains($desc, 'download') => 'bg-primary-fixed text-on-primary-fixed-variant',
                                    str_contains($desc, 'banned') => 'bg-red-100 text-red-700',
                                    str_contains($desc, 'approved') => 'bg-emerald-100 text-emerald-700',
                                    default => 'bg-secondary-container text-on-secondary-container',
                                };
                            @endphp
                            <span class="px-3 py-1 {{ $badgeClass }} text-[10px] font-bold rounded-full whitespace-nowrap uppercase tracking-tighter">
                                {{ Str::limit($a->description, 20) }}
                            </span>
                        </td>

                        {{-- User Context --}}
                        <td class="px-8 py-5">
                            <div class="flex items-center gap-2">
                                @php
                                    $causerName = $a->causer?->name ?? __('vault.system');
                                    $initials = collect(explode(' ', $causerName))->map(fn($w) => strtoupper(substr($w, 0, 1)))->take(2)->join('');
                                    $colors = ['bg-blue-100 text-blue-600', 'bg-purple-100 text-purple-600', 'bg-emerald-100 text-emerald-600', 'bg-amber-100 text-amber-600', 'bg-rose-100 text-rose-600', 'bg-slate-100 text-slate-600'];
                                    $colorIdx = $a->causer_id ? crc32($a->causer_id) % count($colors) : 5;
                                @endphp
                                <div class="w-6 h-6 rounded-full {{ $colors[$colorIdx] }} flex items-center justify-center text-[10px] font-bold">
                                    {{ $initials }}
                                </div>
                                <p class="text-sm font-medium">{{ $causerName }}</p>
                            </div>
                        </td>

                        {{-- Changes (Old → New) --}}
                        <td class="px-8 py-5">
                            @if($a->properties && ($a->properties->has('old') || $a->properties->has('attributes')))
                                <div x-data="{ open: false }" class="relative">
                                    <button @click="open = !open" class="text-[10px] text-on-surface-variant font-mono bg-surface-container px-2 py-1 rounded hover:bg-surface-container-high transition-colors cursor-pointer">
                                        @if($a->properties->has('old'))
                                            {{ count($a->properties['old']) }} field(s)
                                        @else
                                            {{ count($a->properties['attributes'] ?? []) }} field(s)
                                        @endif
                                        <span class="material-symbols-outlined text-[10px] align-middle">expand_more</span>
                                    </button>
                                    <div x-show="open" @click.away="open = false" x-cloak
                                         class="absolute z-30 top-full {{ app()->getLocale() === 'ar' ? 'right-0' : 'left-0' }} mt-2 bg-white rounded-xl shadow-xl border border-outline-variant/20 p-4 min-w-[300px] max-w-[450px]">
                                        <div class="text-[10px] font-bold uppercase tracking-widest text-on-surface-variant mb-3">Change Details</div>
                                        <div class="space-y-2 max-h-[200px] overflow-y-auto custom-scrollbar">
                                            @if($a->properties->has('old'))
                                                @foreach($a->properties['old'] as $key => $oldVal)
                                                    <div class="flex gap-2 text-[11px]">
                                                        <span class="font-semibold text-on-surface min-w-[80px]">{{ $key }}:</span>
                                                        <span class="text-red-500 line-through">{{ Str::limit(is_array($oldVal) ? json_encode($oldVal) : (string)$oldVal, 50) }}</span>
                                                        <span class="text-on-surface-variant">→</span>
                                                        <span class="text-emerald-600">{{ Str::limit(is_array($a->properties['attributes'][$key] ?? '') ? json_encode($a->properties['attributes'][$key] ?? '') : (string)($a->properties['attributes'][$key] ?? '—'), 50) }}</span>
                                                    </div>
                                                @endforeach
                                            @elseif($a->properties->has('attributes'))
                                                @foreach($a->properties['attributes'] as $key => $val)
                                                    <div class="flex gap-2 text-[11px]">
                                                        <span class="font-semibold text-on-surface min-w-[80px]">{{ $key }}:</span>
                                                        <span class="text-emerald-600">{{ Str::limit(is_array($val) ? json_encode($val) : (string)$val, 80) }}</span>
                                                    </div>
                                                @endforeach
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            @else
                                <span class="text-[10px] text-outline">—</span>
                            @endif
                        </td>

                        {{-- Timestamp --}}
                        <td class="px-8 py-5">
                            <p class="text-sm text-on-surface leading-none">{{ $a->created_at->format('M d, Y') }}</p>
                            <p class="text-[10px] text-on-surface-variant mt-1">{{ $a->created_at->format('H:i:s') }} GMT</p>
                        </td>

                        {{-- Actions --}}
                        <td class="px-8 py-5 text-right">
                            <div class="flex justify-end gap-2 opacity-0 group-hover:opacity-100 transition-opacity">
                                @if($a->properties && $a->properties->count() > 0)
                                    <button onclick="alert(JSON.stringify(@js($a->properties), null, 2))" 
                                            class="p-2 hover:bg-surface-container rounded-lg text-on-surface-variant transition-colors" 
                                            title="View JSON">
                                        <span class="material-symbols-outlined text-sm">data_object</span>
                                    </button>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-8 py-20 text-center">
                            <span class="material-symbols-outlined text-5xl text-outline block mb-3">inbox</span>
                            <p class="text-sm font-semibold text-on-surface-variant">{{ __('vault.no_activity') }}</p>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Pagination --}}
    @if($activities->hasPages())
        <div class="px-8 py-6 flex items-center justify-between bg-surface-container-low/30">
            <p class="text-[10px] text-on-surface-variant font-bold uppercase tracking-widest">
                {{ $activities->firstItem() }} - {{ $activities->lastItem() }} / {{ number_format($activities->total()) }}
            </p>
            <div class="flex gap-2">
                {{-- Previous --}}
                @if($activities->onFirstPage())
                    <span class="w-8 h-8 flex items-center justify-center rounded-lg border border-outline-variant text-outline cursor-not-allowed">
                        <span class="material-symbols-outlined text-sm {{ app()->getLocale() === 'ar' ? 'rotate-180' : '' }}">chevron_left</span>
                    </span>
                @else
                    <a href="{{ $activities->previousPageUrl() }}" class="w-8 h-8 flex items-center justify-center rounded-lg border border-outline-variant hover:bg-white transition-colors">
                        <span class="material-symbols-outlined text-sm {{ app()->getLocale() === 'ar' ? 'rotate-180' : '' }}">chevron_left</span>
                    </a>
                @endif

                {{-- Page Numbers --}}
                @foreach($activities->getUrlRange(max(1, $activities->currentPage() - 1), min($activities->lastPage(), $activities->currentPage() + 1)) as $page => $url)
                    @if($page == $activities->currentPage())
                        <span class="w-8 h-8 flex items-center justify-center rounded-lg bg-primary text-white text-[10px] font-bold">{{ $page }}</span>
                    @else
                        <a href="{{ $url }}" class="w-8 h-8 flex items-center justify-center rounded-lg hover:bg-white transition-colors text-[10px] font-bold">{{ $page }}</a>
                    @endif
                @endforeach

                {{-- Next --}}
                @if($activities->hasMorePages())
                    <a href="{{ $activities->nextPageUrl() }}" class="w-8 h-8 flex items-center justify-center rounded-lg border border-outline-variant hover:bg-white transition-colors">
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
</section>
@endsection
