@extends('postal.layout')
@section('title','تحصيل فاتورة الهوية التجريبية')
@section('content')
<h2>البحث عن فاتورة</h2>
@if(session('status'))<p role="status">{{ session('status') }}</p>@endif
<form method="GET" action="{{ route('postal.collections.show') }}"><label>رقم الفاتورة<input name="invoice" maxlength="50" value="{{ request('invoice') }}" required></label><button>بحث / التحقق من السند</button></form>
@if($charge)
<section class="postal-card"><h2>الفاتورة <bdi>{{ $charge->invoice_number }}</bdi></h2><p>قيمة ثابتة: {{ $charge->amount }} {{ $charge->currency }}</p><p>{{ $charge->statusLabel() }}</p>
@if($receipt)<p>السند: <bdi>{{ $receipt->receipt_reference }}</bdi> — {{ $receipt->collected_at->format('Y-m-d H:i') }}</p><p>تم السداد؛ لا تستلم النقد مرة أخرى.</p>
@elseif($charge->status==='due' && $charge->application->status==='accepted')
<form method="POST" action="{{ route('postal.collections.store') }}">@csrf<input type="hidden" name="invoice" value="{{ $charge->invoice_number }}"><input type="hidden" name="operation_token" value="{{ \Illuminate\Support\Str::uuid() }}"><label><input type="checkbox" name="cash_received" value="1" required> استلمت القيمة نقدًا وأؤكد التحصيل مرة واحدة</label><button>تأكيد التحصيل وإصدار السند</button></form>
@else<p>الفاتورة غير متاحة للتحصيل.</p>@endif</section>
@elseif(request('invoice'))<p>لم تُعثر على فاتورة متاحة بهذا الرقم.</p>@endif
<p><a href="{{ route('postal.home') }}">العودة إلى بوابة البريد</a></p>
@endsection
