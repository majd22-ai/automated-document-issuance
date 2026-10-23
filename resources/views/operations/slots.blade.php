@extends('layouts.public')
@section('title','فترات المواعيد')
@section('content')
<section class="section"><div class="container"><h1>تعريف فترات المواعيد وسعتها</h1>@include('operations.messages')<p>الأوقات المدخلة بتوقيت {{ config('operations.timezone') }}. لا توجد مواعيد افتراضية؛ أدخل الفترات الفعلية المعتمدة من المركز.</p>
<form method="POST" action="{{ route('employee.appointment-slots.store') }}" class="card" style="padding:1.5rem">@csrf
<label for="slot-service">الخدمة</label><select class="form-control" name="government_service_id" id="slot-service" required>@foreach($services as $service)<option value="{{ $service->id }}">{{ $service->name }}</option>@endforeach</select>
<label for="slot-center">مركز الخدمة أو الجهة</label><input class="form-control" name="center" id="slot-center" maxlength="200" required>
<label for="slot-start">بداية الفترة</label><input class="form-control" type="datetime-local" name="starts_at" id="slot-start" required>
<label for="slot-end">نهاية الفترة</label><input class="form-control" type="datetime-local" name="ends_at" id="slot-end" required>
<label for="slot-capacity">السعة</label><input class="form-control" name="capacity" id="slot-capacity" type="number" min="1" max="1000" required><button class="btn btn-primary">إضافة الفترة</button></form>
@forelse($slots as $slot)<article class="card mb-3" style="padding:1.5rem"><h2>{{ $slot->service->name }} — {{ $slot->center }}</h2><p>{{ $slot->starts_at->setTimezone(config('operations.timezone'))->format('Y-m-d H:i') }} — {{ $slot->is_active ? 'مفعّلة' : 'ملغاة' }} — الحجوزات {{ $slot->bookings_count }} من {{ $slot->capacity }}</p>
<form method="POST" action="{{ route('employee.appointment-slots.update',$slot) }}">@csrf @method('PATCH')<label for="capacity-{{ $slot->id }}">السعة</label><input class="form-control" name="capacity" id="capacity-{{ $slot->id }}" type="number" min="1" max="1000" value="{{ $slot->capacity }}" required><input type="hidden" name="is_active" value="0"><label><input type="checkbox" name="is_active" value="1" @checked($slot->is_active)> الفترة مفعّلة</label><button class="btn btn-outline">حفظ السعة والحالة</button></form></article>@empty<p>لا توجد فترات معرفة بعد.</p>@endforelse {{ $slots->links() }}
</div></section>
@endsection
