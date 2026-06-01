# 🛡️ opalshot — منصة التصوير السحابي الآمنة

<p align="center">
  <img src="https://img.shields.io/badge/opalshot-API%20v2.5-blueviolet?style=for-the-badge&logo=laravel&logoColor=white" alt="opalshot API">
  <img src="https://img.shields.io/badge/Laravel-11.x-FF2D20?style=for-the-badge&logo=laravel&logoColor=white" alt="Laravel 11">
  <img src="https://img.shields.io/badge/New%20Relic-Observability-008C99?style=for-the-badge&logo=new-relic&logoColor=white" alt="New Relic">
  <img src="https://img.shields.io/badge/MongoDB-Powered-47A248?style=for-the-badge&logo=mongodb&logoColor=white" alt="MongoDB">
</p>

<p align="right" dir="rtl">
  <b>منصة سحابية احترافية للمصورين، تم تطويرها باستخدام تقنيات حديثة تضمن الأمان الفائق، الأداء العالي، والرصد اللحظي للأداء والذكاء الاصطناعي.</b>
</p>

---

## 📌 نظرة عامة
**opalshot** هو مشروع متكامل يوفر بيئة استضافة صور آمنة تضمن للمصورين حماية حقوقهم وإدارة أعمالهم بكفاءة عالية. يتميز النظام بالسرعة الفائقة بفضل وجود طبقات كاش متقدمة وتكامل مع أفضل خدمات الـ CDN، بالإضافة إلى نظام محادثات متطور ورصد شامل للأخطاء والأداء.

---

## ⚡ الميزات الأساسية (آخر التحديثات 🚀)

### 🔒 الأمان والخصوصية المتقدمة
*   **حماية SecureShield v3.0:** نظام علامات مائية ذكي يحمي الصور من السرقة مع الحفاظ على جودتها الجمالية.
*   **الدخول الموحد (OAuth 2.0):** دعم الدخول عبر (Google, Instagram, Facebook, Adobe) مع مزامنة تلقائية لبيانات الملف الشخصي.
*   **تشفير الروابط (Shared Links):** روابط مشاركة محمية بكلمات مرور وتاريخ صلاحية محدد عبر Redis.
*   **سلامة العمليات:** ضمان كامل لسلامة البيانات (Transactional Integrity) عند الرفع لضمان عدم وجود ملفات "يتيمة" في S3.

### 🤖 الذكاء الاصطناعي والتوصيات
*   **محرك التوصيات الهجين:** خوارزمية ذكية توزع المحتوى بين (المقترح، الجديد، والترند) مع منع تكرار المحتوى الذي تمت مشاهدته.
*   **التنوع البصري (Anti-Clustering):** خوارزمية تضمن عدم تكرار صور نفس المصور بشكل متتالي في صفحة الفيد لضمان تجربة مستخدم ممتعة.
*   **الإشراف الآلي (AI Safety):** فحص تلقائي مزدوج للمحتوى بحثاً عن المخالفات مع سياسة حجر صحي تلقائي.

### 🚀 الأداء والرصد (Observability)
*   **رصد New Relic:** تكامل كامل لرصد أداء التطبيق (APM) وتتبع السجلات (Logs) لحظة بلحظة لضمان استقرار بيئة الإنتاج.
*   **هيكلية NoSQL للمحادثات:** استخدام MongoDB لنظام المحادثات لضمان توسعية عالية وسرعة في معالجة آلاف الرسائل المتزامنة.
*   **توزيع الأصول (DO Spaces + ImageKit):** استخدام CDN هجين لضمان وصول الصور للمستخدمين في أقل من 100ms عالمياً.

---

## 📡 نقاط الوصول (API Endpoints) - الإصدار V1

> [!IMPORTANT]
> للحصول على تفاصيل تقنية كاملة لجميع الـ Endpoints، يرجى مراجعة ملف [**API Documentation**](./api_documentation.md).

### أمثلة لأهم المسارات:
*   `POST /api/v1/auth/login` - تسجيل الدخول الموحد.
*   `GET  /api/v1/feed/home` - التغذية الشخصية الذكية.
*   `GET  /api/v1/chat/conversations` - قائمة المحادثات (مبنية على MongoDB).
*   `POST /api/v1/upload/batch` - رفع دفعة صور (Up to 100).

---

## 🛠️ المتطلبات التقنية
- **الباك-إند:** Laravel 11.x, PHP 8.3.
- **قواعد البيانات:** MySQL (للبيانات المترابطة) و MongoDB (للمحادثات والسجلات).
- **التخزين:** DigitalOcean Spaces & ImageKit CDN.
- **الرصد:** New Relic APM & Infrastructure.
- **الرسائل اللحظية:** Laravel Reverb (WebSockets).

---

## 🚀 طريقة التشغيل (Production)
```bash
# بناء وتشغيل الحاويات باستخدام Docker
docker-compose -f docker-compose.prod.yml up -d --build

# تنفيذ عمليات تحسين الأداء
docker exec opalshot-app php artisan optimize
```

---

<p align="center">
  <b>تم التطوير بواسطة فريق opalshot ❤️ - مشروع تخرج 2026</b>
</p>
