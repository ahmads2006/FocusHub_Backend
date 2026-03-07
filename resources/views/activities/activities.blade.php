<x-app-layout>

    <style>
        @import url('https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Arabic:wght@300;400;500;600;700&display=swap');
        .act-wrap * { font-family: 'IBM Plex Sans Arabic', 'Segoe UI', sans-serif; }
        .act-bg { background-color: #0d0f14; min-height: 100vh; }

        /* Table */
        .act-table {
            width: 100%;
            border-collapse: collapse;
        }
        .act-table th {
            font-size: 9px;
            font-weight: 700;
            letter-spacing: 3px;
            text-transform: uppercase;
            color: #3d4460;
            padding: 14px 18px;
            text-align: right;
            border-bottom: 1px solid #1e2130;
            white-space: nowrap;
        }
        .act-table td {
            padding: 16px 18px;
            border-bottom: 1px solid #0d0f14;
            vertical-align: middle;
        }
        .act-table tbody tr {
            transition: background 0.15s;
        }
        .act-table tbody tr:hover { background: #13151c; }
        .act-table tbody tr:last-child td { border-bottom: none; }

        /* Badges */
        .badge {
            display: inline-block;
            font-size: 9px;
            font-weight: 700;
            letter-spacing: 2px;
            text-transform: uppercase;
            padding: 5px 10px;
            border-radius: 6px;
            white-space: nowrap;
        }
        .badge-created  { background: #0a1f10; color: #34d399; border: 1px solid #1a4a22; }
        .badge-updated  { background: #0a0f1f; color: #60a5fa; border: 1px solid #1a2a4a; }
        .badge-deleted  { background: #1a0a0a; color: #f87171; border: 1px solid #4a1515; }
        .badge-default  { background: #13151c; color: #4a5270; border: 1px solid #1e2130; }

        /* Subject pill */
        .subject-pill {
            font-size: 11px;
            font-weight: 600;
            color: #c8cfe0;
        }

        /* Details toggle */
        .details-toggle {
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 1.5px;
            color: #d4a853;
            cursor: pointer;
            text-transform: uppercase;
            list-style: none;
            transition: opacity 0.2s;
        }
        .details-toggle:hover { opacity: 0.7; }
        details summary { list-style: none; }
        details summary::-webkit-details-marker { display: none; }

        .changes-pre {
            margin-top: 10px;
            padding: 12px 14px;
            background: #0d0f14;
            border: 1px solid #1e2130;
            border-radius: 8px;
            font-size: 10px;
            color: #4a5270;
            line-height: 1.8;
            direction: ltr;
            text-align: left;
            overflow-x: auto;
            font-family: 'Courier New', monospace;
        }

        /* Time */
        .time-main { font-size: 12px; color: #8891aa; font-weight: 500; }
        .time-rel  { font-size: 10px; color: #3d4460; margin-top: 3px; }

        /* Empty state */
        .empty-row td {
            padding: 70px 20px;
            text-align: center;
            color: #2a2f45;
            font-size: 14px;
        }
        .empty-icon { font-size: 36px; margin-bottom: 12px; opacity: 0.25; }

        /* Panel */
        .panel {
            background: #13151c;
            border: 1px solid #1e2130;
            border-radius: 16px;
            overflow: hidden;
        }
        .panel-header {
            padding: 24px 28px;
            border-bottom: 1px solid #1e2130;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
        }

        /* Gold top bar */
        .gold-bar {
            height: 2px;
            background: linear-gradient(90deg, transparent, #d4a853 40%, transparent);
        }

        /* Pagination */
        .pagination-wrap nav { --tw-text-opacity: 1; }
        .pagination-wrap .pagination span,
        .pagination-wrap .pagination a {
            background: #13151c !important;
            border-color: #1e2130 !important;
            color: #4a5270 !important;
            border-radius: 8px !important;
        }
        .pagination-wrap .pagination a:hover {
            background: #1e2130 !important;
            color: #c8cfe0 !important;
        }
        .pagination-wrap [aria-current="page"] span {
            background: #d4a853 !important;
            border-color: #d4a853 !important;
            color: #0d0f14 !important;
        }

        /* Live dot */
        .live-dot {
            width: 7px; height: 7px;
            border-radius: 50%;
            background: #34d399;
            display: inline-block;
            margin-left: 8px;
            box-shadow: 0 0 6px #34d39988;
            animation: pulse 2s infinite;
        }
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.4; }
        }
    </style>

    <div class="act-bg act-wrap" dir="rtl">
        <div class="py-10">
            <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">

                <!-- Page heading -->
                <div style="margin-bottom: 28px;">
                    <p style="font-size:10px;font-weight:700;letter-spacing:5px;text-transform:uppercase;color:#d4a853;margin:0 0 6px 0;">OPTICVAULT</p>
                    <h1 style="font-size:26px;font-weight:700;color:#f1f3f9;margin:0;">سجل النشاطات</h1>
                </div>

                <div class="panel">
                    <div class="gold-bar"></div>

                    <!-- Panel header -->
                    <div class="panel-header">
                        <div>
                            <div style="display:flex;align-items:center;gap:4px;">
                                <span style="font-size:14px;font-weight:700;color:#f1f3f9;">تتبع العمليات</span>
                                <span class="live-dot"></span>
                            </div>
                            <p style="font-size:12px;color:#3d4460;margin:4px 0 0 0;">جميع التغييرات التي أجريتها في المشروع</p>
                        </div>
                        <div style="display:flex;gap:8px;flex-wrap:wrap;">
                            @php $counts = ['created' => 0, 'updated' => 0, 'deleted' => 0]; @endphp
                            @foreach($activities as $a)
                                @php if(isset($counts[$a->description])) $counts[$a->description]++; @endphp
                            @endforeach
                            <span class="badge badge-created">إنشاء × {{ $counts['created'] }}</span>
                            <span class="badge badge-updated">تعديل × {{ $counts['updated'] }}</span>
                            <span class="badge badge-deleted">حذف × {{ $counts['deleted'] }}</span>
                        </div>
                    </div>

                    <!-- Table -->
                    <div style="overflow-x: auto;">
                        <table class="act-table">
                            <thead>
                                <tr>
                                    <th>العملية</th>
                                    <th>الهدف</th>
                                    <th>التفاصيل</th>
                                    <th>التاريخ</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($activities as $activity)
                                    @php
                                        $badgeClass = match($activity->description) {
                                            'created' => 'badge-created',
                                            'updated' => 'badge-updated',
                                            'deleted' => 'badge-deleted',
                                            default   => 'badge-default',
                                        };
                                    @endphp
                                    <tr>
                                        <td>
                                            <span class="badge {{ $badgeClass }}">
                                                {{ __($activity->description) }}
                                            </span>
                                        </td>
                                        <td>
                                            <span class="subject-pill">{{ basename($activity->subject_type ?? 'النظام') }}</span>
                                        </td>
                                        <td>
                                            @if($activity->changes())
                                                <details>
                                                    <summary class="details-toggle">عرض التغييرات ↓</summary>
                                                    <pre class="changes-pre">{{ json_encode($activity->changes(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                                                </details>
                                            @else
                                                <span style="font-size:11px;color:#2a2f45;">—</span>
                                            @endif
                                        </td>
                                        <td>
                                            <div class="time-main">{{ $activity->created_at->format('Y-m-d H:i') }}</div>
                                            <div class="time-rel">{{ $activity->created_at->diffForHumans() }}</div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr class="empty-row">
                                        <td colspan="4">
                                            <div class="empty-icon">📋</div>
                                            <p>لا توجد سجلات بعد.</p>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    @if($activities->hasPages())
                        <div class="pagination-wrap" style="padding: 20px 28px; border-top: 1px solid #1e2130;">
                            {{ $activities->links() }}
                        </div>
                    @endif
                </div>

            </div>
        </div>
    </div>
</x-app-layout>