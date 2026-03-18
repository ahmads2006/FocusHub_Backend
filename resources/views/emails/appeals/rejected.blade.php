<x-mail::message>
# قرار بشأن طلب المراجعة الخاص بك

مرحباً **{{ $appeal->contact_name }}**،

نود إعلامك بأنه تم رفض طلب المراجعة الخاص بك المتعلق بالصورة "**{{ $appeal->image->title ?? 'بدون عنوان' }}**".

@if($appeal->admin_notes)
**سبب الرفض وملاحظة الإدارة:**
> {{ $appeal->admin_notes }}
@endif

نأسف لإبلاغك بأن هذا القرار نهائي ولا يمكن تقديم طلب مراجعة آخر لهذه الصورة.

<x-mail::button :url="$url">
عرض سجل المتابعات
</x-mail::button>

مع تحيات،<br>
فريق {{ config('app.name') }}
</x-mail::message>
