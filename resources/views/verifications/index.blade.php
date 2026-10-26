@extends('layouts.public')
@section('title','طلبات التحقق الإلكتروني')
@section('content')
<section class="section"><div class="container"><h1>طلبات التحقق ضمن اختصاصي</h1><p>تظهر فقط الخدمات التي مُنحت صلاحية التحقق الخارجي لها.</p><div class="card" style="padding:1rem">@forelse($applications as $application)<p><a href="{{ route('employee.verifications.show',$application->id) }}">{{ $application->reference }} — {{ $application->service_name }} — {{ $application->statusLabel() }}</a></p>@empty<p>لا توجد طلبات ضمن صلاحية التحقق الممنوحة لك.</p>@endforelse{{ $applications->links() }}</div></div></section>
@endsection
