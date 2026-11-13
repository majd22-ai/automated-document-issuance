@extends('postal.layout')
@section('content')
<p><a href="{{ route('postal.collections.show') }}">تحصيل فاتورة الهوية / التحقق من سند سابق</a></p>
<section class="postal-card"><h2>مرحباً {{ $collector->name }}</h2>
  <dl><dt>المكتب التجريبي</dt><dd>{{ $collector->office->name }}</dd><dt>الرمز</dt><dd>{{ $collector->office->code }}</dd><dt>المنطقة</dt><dd>{{ $collector->office->area }}</dd><dt>العنوان التعليمي</dt><dd>{{ $collector->office->address }}</dd></dl>
  <p>ابحث برقم فاتورة الهوية لتسجيل التحصيل النقدي وإصدار السند. عند انقطاع الاستجابة تحقق من السند المحفوظ قبل استلام النقد مجددًا. قيمة الرسوم ثابتة، وإنشاء الفاتورة من اختصاص موظف مراجعة الطلب.</p>
  <form method="POST" action="{{ route('postal.logout') }}">@csrf<button type="submit">تسجيل الخروج</button></form>
</section>
@endsection
