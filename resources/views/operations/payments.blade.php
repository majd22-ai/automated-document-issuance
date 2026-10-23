@extends('layouts.public')
@section('title','الرسوم والمدفوعات')
@section('content')
<section class="section"><div class="container"><h1>الرسوم والمدفوعات</h1>@include('operations.messages')<p>المدفوعات المعروضة مسجلة يدويًا بواسطة الموظف المخول. لا توجد بوابة دفع إلكترونية ضمن هذا المتطلب.</p>
@forelse($charges as $charge)<article class="card mb-3" style="padding:1.5rem"><h2>{{ $charge->application->service_name }}</h2><p>الطلب {{ $charge->application->reference }} — {{ $charge->amount }} {{ $charge->currency }} — {{ $charge->statusLabel() }}</p><p>المتبقي {{ $charge->status==='due' ? $charge->amount : '0.00' }} {{ $charge->currency }}</p>@if($charge->payment)<p>تاريخ السداد {{ $charge->payment->paid_at->format('Y-m-d') }} — مرجع الإيصال {{ $charge->payment->receipt_reference }}</p>@endif<a href="{{ route(($employee?'employee':'citizen').'.applications.operations',$charge->citizen_service_request_id) }}">تفاصيل رسوم الطلب</a></article>@empty<p>لا توجد التزامات مالية مسجلة. الرسوم غير المحددة لا تُعتبر رسومًا صفرية.</p>@endforelse {{ $charges->links() }}
</div></section>
@endsection
