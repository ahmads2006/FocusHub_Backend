<x-mail::message>
# قرار بشأن طلب المراجعة الخاص بك

مرحباً **{{ $appeal->contact_name }}**،

نود إعلامك بأنه تم قبول طلب المراجعة الخاص بك، وتم إلغاء الحظر عن صورتك "**{{ $appeal->image->title ?? 'بدون عنوان' }}**".

@if($appeal->admin_notes)
**ملاحظة من الإدارة:**
> {{ $appeal->admin_notes }}
@endif

أصبحت الصورة الآن متاحة ويمكنك عرضها في مكتبتك.

<x-mail::button :url="$url">
عرض الصورة
</x-mail::button>

مع تحيات،<br>
فريق {{ config('app.name') }}
</x-mail::message>
