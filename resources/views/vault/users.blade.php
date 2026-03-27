@extends('layouts.vault')

@section('title', __('vault.users') . ' — ' . __('vault.audit_center'))
@section('page-title', __('vault.users'))
@section('page-subtitle', __('vault.users_audit_subtitle'))

@section('content')
{{-- ═══════════ Users Management Table ═══════════ --}}
<section class="bg-surface-container-lowest rounded-xl shadow-sm overflow-hidden text-sm">
    <div class="p-8 border-b border-outline-variant/15">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-6">
            <div>
                <h2 class="font-headline font-bold text-2xl text-on-surface">{{ __('vault.users') }}</h2>
                <p class="text-xs text-on-surface-variant mt-1">{{ __('vault.users_audit_subtitle') }}</p>
            </div>
            <div class="flex items-center gap-3">
                <form method="GET" action="{{ route('vault.users') }}" class="flex items-center gap-3">
                    <div class="relative">
                        <span class="material-symbols-outlined absolute {{ app()->getLocale() === 'ar' ? 'right-3' : 'left-3' }} top-1/2 -translate-y-1/2 text-slate-400 text-sm">search</span>
                        <input name="search" value="{{ request('search') }}"
                               class="bg-surface border-none rounded-lg {{ app()->getLocale() === 'ar' ? 'pr-10 pl-4' : 'pl-10 pr-4' }} py-2 text-xs focus:ring-2 focus:ring-blue-500/20 transition-all w-64" 
                               placeholder="{{ __('vault.search_users') }}" type="text"/>
                    </div>
                    <select name="status" onchange="this.form.submit()" class="bg-surface border-none rounded-lg text-xs text-on-surface py-2 px-4 focus:ring-2 focus:ring-blue-500/20 transition-all">
                        <option value="">{{ __('vault.all_statuses') }}</option>
                        <option value="active" {{ request('status') == 'active' ? 'selected' : '' }}>{{ __('vault.active') }}</option>
                        <option value="shadow" {{ request('status') == 'shadow' ? 'selected' : '' }}>{{ __('vault.shadow_hidden') }}</option>
                    </select>
                    <button type="submit" class="px-4 py-2 bg-on-surface text-white text-xs font-bold rounded-lg hover:opacity-90 transition-all uppercase tracking-widest">
                        {{ __('vault.filter') }}
                    </button>
                </form>
            </div>
        </div>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="bg-surface-container-low">
                    <th class="px-8 py-4 text-[10px] font-bold uppercase tracking-widest text-on-surface-variant">{{ __('vault.user') }}</th>
                    <th class="px-8 py-4 text-[10px] font-bold uppercase tracking-widest text-on-surface-variant">Email</th>
                    <th class="px-8 py-4 text-[10px] font-bold uppercase tracking-widest text-on-surface-variant">{{ __('vault.roles') }}</th>
                    <th class="px-8 py-4 text-[10px] font-bold uppercase tracking-widest text-on-surface-variant">{{ __('vault.status') }}</th>
                    <th class="px-8 py-4 text-[10px] font-bold uppercase tracking-widest text-on-surface-variant">{{ __('vault.timestamp') }}</th>
                    <th class="px-8 py-4 text-[10px] font-bold uppercase tracking-widest text-on-surface-variant text-right">{{ __('vault.actions') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-outline-variant/10">
                @forelse($users as $user)
                    <tr class="hover:bg-surface-container-high transition-colors group">
                        <td class="px-8 py-5">
                            <div class="flex items-center gap-4">
                                <img src="{{ $user->avatar }}" alt="{{ $user->name }}" class="w-8 h-8 rounded-full object-cover bg-surface-container shadow-sm">
                                <div>
                                    <p class="text-sm font-bold text-on-surface leading-tight">{{ $user->name }}</p>
                                    <p class="text-[10px] text-on-surface-variant">ID: {{ Str::limit($user->id, 8) }}</p>
                                </div>
                            </div>
                        </td>
                        <td class="px-8 py-5">
                            <p class="text-sm text-on-surface">{{ $user->email }}</p>
                        </td>
                        <td class="px-8 py-5">
                            @php $roleName = $user->roles->first()?->name ?? $user->role ?? 'user'; @endphp
                            <span class="px-3 py-1 text-[10px] font-bold rounded-full uppercase tracking-tighter
                                {{ $roleName === 'super-admin' || $user->role === 'super_admin' ? 'bg-purple-100 text-purple-700' : 'bg-secondary-container text-on-secondary-container' }}">
                                {{ str_replace(['-', '_'], ' ', $roleName) }}
                            </span>
                        </td>
                        <td class="px-8 py-5">
                            @if($user->is_banned)
                                <span class="px-3 py-1 bg-red-100 text-red-700 text-[10px] font-bold rounded-full flex items-center gap-1 w-fit uppercase tracking-tighter">
                                    {{ __('vault.banned') }}
                                </span>
                            @elseif($user->is_shadow_hidden)
                                <span class="px-3 py-1 bg-amber-100 text-amber-700 text-[10px] font-bold rounded-full flex items-center gap-1 w-fit uppercase tracking-tighter">
                                    {{ __('vault.shadow_hidden') }}
                                </span>
                            @else
                                <span class="px-3 py-1 bg-emerald-100 text-emerald-700 text-[10px] font-bold rounded-full flex items-center gap-1 w-fit uppercase tracking-tighter">
                                    {{ __('vault.active') }}
                                </span>
                            @endif
                        </td>
                        <td class="px-8 py-5">
                            <p class="text-sm text-on-surface leading-none">{{ $user->created_at->format('M d, Y') }}</p>
                            <p class="text-[10px] text-on-surface-variant mt-1">{{ $user->created_at->diffForHumans() }}</p>
                        </td>
                        <td class="px-8 py-5 text-right">
                            <div class="flex justify-end gap-2 opacity-0 group-hover:opacity-100 transition-opacity">
                                <a href="{{ route('vault.operations', ['username' => $user->name]) }}" 
                                   class="p-2 hover:bg-surface-container rounded-lg text-on-surface-variant transition-colors" title="{{ __('vault.view_logs') }}">
                                    <span class="material-symbols-outlined text-sm">history</span>
                                </a>
                                <a href="{{ route('admin.users.index', ['search' => $user->email]) }}" 
                                   class="p-2 hover:bg-surface-container rounded-lg text-on-surface-variant transition-colors" title="Manage">
                                    <span class="material-symbols-outlined text-sm">open_in_new</span>
                                </a>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-8 py-20 text-center">
                            <span class="material-symbols-outlined text-5xl text-outline block mb-3">person_search</span>
                            <p class="text-sm font-semibold text-on-surface-variant">{{ __('vault.no_users') }}</p>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Pagination --}}
    @if($users->hasPages())
        <div class="px-8 py-6 flex items-center justify-between bg-surface-container-low/30">
            <p class="text-[10px] text-on-surface-variant font-bold uppercase tracking-widest">
                {{ $users->firstItem() }} - {{ $users->lastItem() }} / {{ number_format($users->total()) }}
            </p>
            <div class="flex gap-2">
                @if($users->onFirstPage())
                    <span class="w-8 h-8 flex items-center justify-center rounded-lg border border-outline-variant text-outline cursor-not-allowed">
                        <span class="material-symbols-outlined text-sm {{ app()->getLocale() === 'ar' ? 'rotate-180' : '' }}">chevron_left</span>
                    </span>
                @else
                    <a href="{{ $users->previousPageUrl() }}" class="w-8 h-8 flex items-center justify-center rounded-lg border border-outline-variant hover:bg-white transition-colors">
                        <span class="material-symbols-outlined text-sm {{ app()->getLocale() === 'ar' ? 'rotate-180' : '' }}">chevron_left</span>
                    </a>
                @endif

                @foreach($users->getUrlRange(max(1, $users->currentPage() - 1), min($users->lastPage(), $users->currentPage() + 1)) as $page => $url)
                    @if($page == $users->currentPage())
                        <span class="w-8 h-8 flex items-center justify-center rounded-lg bg-primary text-white text-[10px] font-bold">{{ $page }}</span>
                    @else
                        <a href="{{ $url }}" class="w-8 h-8 flex items-center justify-center rounded-lg hover:bg-white transition-colors text-[10px] font-bold">{{ $page }}</a>
                    @endif
                @endforeach

                @if($users->hasMorePages())
                    <a href="{{ $users->nextPageUrl() }}" class="w-8 h-8 flex items-center justify-center rounded-lg border border-outline-variant hover:bg-white transition-colors">
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
