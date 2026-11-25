@extends('layouts.public')
@section('title', 'الاستعلام عن حالة طلب')
@section('content')
<section class="section" dir="rtl">
    <div class="gov-container">
        <div class="card gov-front-form">
            <h1>الاستعلام عن حالة طلب</h1>
            <form method="GET" action="{{ route('inquiry.reference') }}">
                <div class="form-group">
                    <label class="form-label" for="inquiry-service">الخدمة</label>
                    <select class="form-control" id="inquiry-service" name="service" required>
                        <option value="">اختر الخدمة</option>
                        @foreach($services as $service)
                            <option value="{{ $service->id }}" @selected((string) old('service', $filters['service'] ?? '') === (string) $service->id)>{{ $service->name }}</option>
                        @endforeach
                    </select>
                    @error('service')<p role="alert">{{ $message }}</p>@enderror
                </div>
                <div class="form-group">
                    <label class="form-label" for="inquiry-reference">الرقم المرجعي</label>
                    <input class="form-control" id="inquiry-reference" type="search" name="reference"
                           value="{{ old('reference', $filters['reference'] ?? '') }}" maxlength="100" dir="ltr" required>
                    @error('reference')<p role="alert">{{ $message }}</p>@enderror
                </div>
                @if($searching)<p role="status">لم يُعثر على طلب في حسابك بهذه الخدمة والرقم المرجعي. راجع المدخلات وحاول مرة أخرى.</p>@endif
                <button class="btn btn-primary" type="submit">استعلام</button>
                <a class="btn btn-outline" href="{{ route('citizen.applications.index') }}">جميع طلباتي</a>
            </form>
        </div>
    </div>
</section>
@endsection
