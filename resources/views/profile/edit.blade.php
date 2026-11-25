@extends('layouts.public')
@section('title', 'الملف الشخصي - منصة الإصدار الآلي')
@section('content')
<section class="section">
  <div class="gov-container">
    <div class="card gov-front-form">
      <h2>الملف الشخصي</h2>
      @if($user->role === 'citizen')
        <p>{{ $civilRecord ? 'البيانات الشخصية من السجل المدني التجريبي للعرض فقط.' : 'بيانات الحساب للعرض فقط. لا يوجد قيد تجريبي مرتبط بالحساب.' }}</p>
        <dl class="form-group">
          <dt>الاسم الكامل</dt><dd>{{ $civilRecord?->name ?? $user->name }}</dd>
          <dt>الرقم الوطني</dt><dd><bdi>{{ ($civilRecord ? $civilRecord->national_id : $user->nationalIdentity()) ?: 'غير مسجل' }}</bdi></dd>
          @if($civilRecord)
            @foreach(['birth_date' => 'تاريخ الميلاد', 'birth_place' => 'مكان الميلاد', 'father_name' => 'اسم الأب', 'mother_name' => 'اسم الأم'] as $field => $label)
              <dt>{{ $label }}</dt><dd>{{ $civilRecord->{$field} ?: 'غير مسجل' }}</dd>
            @endforeach
          @endif
          <dt>رقم الهاتف</dt><dd><bdi>{{ ($civilRecord ? $civilRecord->phone : $user->phone) ?: 'غير مسجل' }}</bdi></dd>
          <dt>البريد الإلكتروني للحساب</dt><dd><bdi>{{ $user->email ?: 'غير مسجل' }}</bdi></dd>
        </dl>
        <a class="btn btn-outline" href="{{ route('citizen.dashboard') }}">لوحة التحكم</a>
      @else
      <p>يمكنك تحديث اسمك وبريدك الإلكتروني ورقم هاتفك.</p>
      @if(session('status'))
        <div class="alert alert-success" role="status">{{ session('status') }}</div>
      @endif
      @if($errors->any())
        <div class="alert alert-danger" role="alert"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
      @endif
      <form method="POST" action="{{ route('profile.update') }}">
        @csrf
        @method('PATCH')
        <div class="form-group">
          <label class="form-label" for="profile-name">الاسم الكامل</label>
          <input class="form-control" id="profile-name" name="name" value="{{ old('name', $user->name) }}" maxlength="100" autocomplete="name" required>
        </div>
        <div class="form-group">
          <label class="form-label" for="profile-email">البريد الإلكتروني</label>
          <input class="form-control" type="email" id="profile-email" name="email" value="{{ old('email', $user->email) }}" maxlength="255" autocomplete="email" required>
        </div>
        <div class="form-group">
          <label class="form-label" for="profile-phone">رقم الهاتف (اختياري)</label>
          <input class="form-control" type="tel" id="profile-phone" name="phone" value="{{ old('phone', $user->phone) }}" maxlength="30" autocomplete="tel" dir="ltr">
        </div>
        <p>اسم المستخدم: {{ $user->username ?? 'غير مسجل' }}</p>
        <p>رقم الهوية: {{ $user->nationalIdentity() ?? 'غير مسجل' }}</p>
        <button class="btn btn-primary" type="submit">حفظ التغييرات</button>
        <a class="btn btn-outline" href="{{ route(auth()->user()->role.'.dashboard') }}">لوحة التحكم</a>
      </form>
      @endif
    </div>
  </div>
</section>
@endsection
