@extends('layouts.premium')

@section('title', 'إدارة المحتوى والمراجعة - OpticVault')

@section('content')
<div class="px-6 py-8">
    <div class="mb-10 flex items-center justify-between">
        <div class="flex items-center gap-4">
            <div>
                <h1 class="text-3xl font-bold text-white tracking-tight">إدارة مراجعة الصور</h1>
                <p class="mt-2 text-slate-400">راجع الصور المشبوهة، التقارير، واتخذ الإجراءات اللازمة لضمان سلامة المجتمع.</p>
            </div>
            <div class="mr-auto">
                <a href="{{ route('admin.photos.index') }}" class="px-6 py-2 bg-purple-600 hover:bg-purple-700 text-white text-xs font-bold rounded-xl transition-all uppercase tracking-widest shadow-lg shadow-purple-500/20">
                    إدارة جميع الصور ←
                </a>
            </div>
        </div>
        
        <div class="flex space-x-3 space-x-reverse">
            <span class="px-4 py-2 bg-slate-800 rounded-full text-slate-300 text-sm border border-slate-700">
                <i class="fas fa-clock ml-2"></i> معلقة: {{ $pendingImages->total() }}
            </span>
            <span class="px-4 py-2 bg-slate-800 rounded-full text-slate-300 text-sm border border-slate-700">
                <i class="fas fa-flag ml-2"></i> بلاغات مفتوحة: {{ $reports->total() }}
            </span>
        </div>
    </div>

    <!-- Tabs/Sections -->
    <div class="space-y-12">
        
        <!-- Pending Review Section -->
        <section>
            <div class="flex items-center mb-6 border-b border-slate-800 pb-4">
                <div class="w-2 h-8 bg-amber-500 rounded-full mr-3"></div>
                <h2 class="text-xl font-bold text-white">صور في انتظار المراجعة (AI Flags)</h2>
            </div>

            @if($pendingImages->isEmpty())
                <div class="p-12 bg-slate-900 rounded-2xl border border-slate-800 text-center">
                    <p class="text-slate-500">لا توجد صور بانتظار المراجعة حالياً. النظام آمن! ✅</p>
                </div>
            @else
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    @foreach($pendingImages as $image)
                        <div class="bg-slate-900 rounded-2xl border border-slate-800 overflow-hidden group hover:border-amber-500/30 transition-all duration-300 shadow-xl">
                            <div class="aspect-video relative overflow-hidden bg-slate-800">
                                <img src="{{ $image->url }}" class="w-full h-full object-cover">
                                <div class="absolute top-3 left-3 px-3 py-1 bg-amber-500/90 text-black text-xs font-bold rounded-full">
                                    {{ strtoupper($image->status) }}
                                </div>
                            </div>
                            
                            <div class="p-5">
                                <div class="flex justify-between items-start mb-4">
                                    <div>
                                        <p class="text-white font-semibold truncate">{{ $image->title }}</p>
                                        <p class="text-xs text-slate-500">بواسطة: {{ $image->user->name }}</p>
                                    </div>
                                    <div class="text-right">
                                        <p class="text-[10px] text-slate-500 uppercase">AI Metadata</p>
                                        <div class="flex gap-1 mt-1">
                                            @php $ai = $image->ai_metadata['checks'] ?? []; @endphp
                                            <span class="w-2 h-2 rounded-full {{ ($ai['skin_heuristic'] ?? '') === 'flagged' ? 'bg-red-500' : 'bg-green-500' }}" title="Skin Heuristic"></span>
                                            <span class="w-2 h-2 rounded-full {{ ($ai['local_ml'] ?? '') === 'flagged' ? 'bg-red-500' : 'bg-green-500' }}" title="Local AI"></span>
                                        </div>
                                    </div>
                                </div>

                                <div class="grid grid-cols-2 gap-3">
                                    <form action="{{ route('admin.photos.approve', $image) }}" method="POST">
                                        @csrf
                                        <button class="w-full py-2 bg-green-600 hover:bg-green-700 text-white text-sm font-bold rounded-xl transition-colors">
                                            <i class="fas fa-check ml-1"></i> اعتماد
                                        </button>
                                    </form>
                                    <form action="{{ route('admin.photos.reject', $image) }}" method="POST">
                                        @csrf
                                        <button class="w-full py-2 bg-red-600/20 hover:bg-red-600 text-red-500 hover:text-white text-sm font-bold rounded-xl border border-red-600/30 transition-all">
                                            <i class="fas fa-times ml-1"></i> حجب
                                        </button>
                                    </form>
                                </div>
                                <div class="mt-3">
                                    <form action="{{ route('admin.users.ban_system', $image->user) }}" method="POST" onsubmit="return confirm('هل أنت متأكد من حظر هذا المستخدم نهائياً؟')">
                                        @csrf
                                        <button class="w-full text-[11px] text-slate-500 hover:text-red-400 transition-colors uppercase tracking-widest">
                                            <i class="fas fa-user-slash mr-1"></i> Ban User
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
                <div class="mt-6">
                    {{ $pendingImages->links() }}
                </div>
            @endif
        </section>

        <!-- Reports Section -->
        <section>
            <div class="flex items-center mb-6 border-b border-slate-800 pb-4">
                <div class="w-2 h-8 bg-red-500 rounded-full mr-3"></div>
                <h2 class="text-xl font-bold text-white">بلاغات المستخدمين (User Reports)</h2>
            </div>

            @if($reports->isEmpty())
                <div class="p-12 bg-slate-900 rounded-2xl border border-slate-800 text-center">
                    <p class="text-slate-500">لا توجد بلاغات حالياً. مجتمعنا نظيف! ✨</p>
                </div>
            @else
                <div class="bg-slate-900 rounded-2xl border border-slate-800 overflow-hidden shadow-xl">
                    <table class="w-full text-right">
                        <thead class="bg-slate-800/50">
                            <tr>
                                <th class="px-6 py-4 text-slate-300 text-sm font-semibold">الصورة</th>
                                <th class="px-6 py-4 text-slate-300 text-sm font-semibold">المُبلّغ</th>
                                <th class="px-6 py-4 text-slate-300 text-sm font-semibold">السبب</th>
                                <th class="px-6 py-4 text-slate-300 text-sm font-semibold">التاريخ</th>
                                <th class="px-6 py-4 text-slate-300 text-sm font-semibold">الإجراء</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-800">
                            @foreach($reports as $report)
                                <tr class="hover:bg-slate-800/30 transition-colors">
                                    <td class="px-6 py-4">
                                        <div class="flex items-center">
                                            <img src="{{ $report->image->getThumbnailUrl('small') }}" class="w-12 h-12 rounded-lg object-cover ring-1 ring-slate-700">
                                            <span class="mr-3 text-slate-300 text-sm truncate max-w-[100px]">{{ $report->image->title }}</span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 text-slate-300 text-sm">{{ $report->user->name }}</td>
                                    <td class="px-6 py-4">
                                        <span class="px-2 py-1 bg-red-500/10 text-red-400 text-[11px] font-bold rounded border border-red-500/20">
                                            {{ $report->reason }}
                                        </span>
                                        @if($report->details)
                                            <p class="text-[10px] text-slate-500 mt-1 max-w-[150px] truncate" title="{{ $report->details }}">{{ $report->details }}</p>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 text-slate-500 text-xs">{{ $report->created_at->diffForHumans() }}</td>
                                    <td class="px-6 py-4">
                                        <div class="flex space-x-2 space-x-reverse">
                                            <form action="{{ route('admin.reports.resolve', [$report, 'resolve']) }}" method="POST">
                                                @csrf
                                                <button class="p-2 bg-slate-800 hover:bg-green-600 rounded-lg text-slate-400 hover:text-white transition-all">
                                                    <i class="fas fa-check text-xs"></i>
                                                </button>
                                            </form>
                                            <form action="{{ route('admin.reports.resolve', [$report, 'dismiss']) }}" method="POST">
                                                @csrf
                                                <button class="p-2 bg-slate-800 hover:bg-slate-700 rounded-lg text-slate-400 hover:text-white transition-all">
                                                    <i class="fas fa-trash text-xs"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="mt-6">
                    {{ $reports->links() }}
                </div>
            @endif
        </section>

    </div>
</div>

<style>
    .rounded-2xl { border-radius: 1rem; }
    .rounded-xl { border-radius: 0.75rem; }
</style>
@endsection
