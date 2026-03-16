<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OpticVault | نظام التشخيص الذكي</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Tajawal', sans-serif; background-color: #0f172a; color: #f8fafc; }
        .glass { background: rgba(30, 41, 59, 0.7); backdrop-filter: blur(10px); border: 1px solid rgba(255,255,255,0.1); }
    </style>
</head>
<body class="p-8">
    <div class="max-w-6xl mx-auto">
        <header class="mb-10 flex justify-between items-center">
            <div>
                <h1 class="text-3xl font-bold text-blue-400 mb-2">نظام التشخيص والمراقبة الذكي</h1>
                <p class="text-slate-400">تحليل العمليات التي تحدث في الخلفية واكتشاف الأخطاء التقنية.</p>
            </div>
            <button onclick="refreshData()" class="bg-blue-600 hover:bg-blue-500 px-6 py-2 rounded-lg transition shadow-lg">تحديث البيانات</button>
        </header>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-10">
            <div id="db-status" class="glass p-6 rounded-2xl shadow-xl">
                <h3 class="text-slate-400 text-sm mb-1">قاعدة البيانات</h3>
                <p class="text-2xl font-bold status-text">جاري الفحص...</p>
            </div>
            <div id="redis-status" class="glass p-6 rounded-2xl shadow-xl">
                <h3 class="text-slate-400 text-sm mb-1">ذاكرة الكاش (Redis)</h3>
                <p class="text-2xl font-bold status-text">جاري الفحص...</p>
            </div>
            <div id="queue-status" class="glass p-6 rounded-2xl shadow-xl">
                <h3 class="text-slate-400 text-sm mb-1">طابور العمليات (Jobs)</h3>
                <p class="text-2xl font-bold status-text">جاري الفحص...</p>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            <div class="glass p-8 rounded-2xl shadow-2xl overflow-hidden">
                <h2 class="text-xl font-bold mb-6 flex items-center">
                    <span class="w-3 h-3 bg-red-500 rounded-full ml-3 animate-pulse"></span>
                    آخر الأخطاء المسجلة (Job Errors)
                </h2>
                <div id="failed-jobs-list" class="space-y-4 max-h-[500px] overflow-y-auto pr-2">
                    <p class="text-slate-500">جاري تحميل تقارير الفشل...</p>
                </div>
            </div>

            <div class="glass p-8 rounded-2xl shadow-2xl">
                <h2 class="text-xl font-bold mb-6 text-blue-300">سجل النظام المباشر (Latest Logs)</h2>
                <div id="system-logs" class="bg-black/50 p-4 rounded-xl font-mono text-xs overflow-auto max-h-[500px] text-slate-300 whitespace-pre scrollbar-thin">
                    جاري سحب السجلات...
                </div>
            </div>
        </div>
    </div>

    <script>
        async function refreshData() {
            try {
                const response = await fetch('/api/health');
                const data = await response.json();

                // Update Status Cards
                updateStatus('db-status', data.database);
                updateStatus('redis-status', data.redis);
                updateStatus('queue-status', data.queue.status + ` (${data.queue.failed_count} فشل)`);

                // Update Failed Jobs
                const failedList = document.getElementById('failed-jobs-list');
                failedList.innerHTML = '';
                if (data.queue.recent_failed && data.queue.recent_failed.length > 0) {
                    data.queue.recent_failed.forEach(job => {
                        failedList.innerHTML += `
                            <div class="bg-red-900/20 border border-red-500/30 p-4 rounded-xl mb-3">
                                <div class="flex justify-between text-xs text-red-400 mb-2">
                                    <span>المعرف: ${job.id}</span>
                                    <span>التوقيت: ${job.failed_at}</span>
                                </div>
                                <p class="text-sm font-mono text-red-200 break-words">${job.exception}</p>
                            </div>
                        `;
                    });
                } else {
                    failedList.innerHTML = '<p class="text-green-400 font-bold">لا توجد عمليات فاشلة مؤخراً. كل شيء يعمل بامتياز!</p>';
                }

                // Update Logs
                const logBox = document.getElementById('system-logs');
                logBox.innerText = data.logs.join('\n');
                logBox.scrollTop = logBox.scrollHeight;

            } catch (error) {
                console.error('Error fetching health data:', error);
            }
        }

        function updateStatus(id, result) {
            const el = document.getElementById(id);
            const textEl = el.querySelector('.status-text');
            const statusStr = typeof result === 'object' ? result.status : result.toString();
            
            if (statusStr === 'OK' || statusStr.startsWith('OK')) {
                textEl.innerText = typeof result === 'object' ? 'متصل بنجاح' : 'متصل بنجاح';
                textEl.className = 'text-2xl font-bold text-green-400 status-text';
            } else {
                textEl.innerText = 'خطأ تقني';
                textEl.className = 'text-2xl font-bold text-red-400 status-text';
            }
        }

        refreshData();
        setInterval(refreshData, 5000); // Auto refresh every 5s
    </script>
</body>
</html>
