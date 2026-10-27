@extends('layouts.public')
@section('title', 'مراجعة المرفقات')
@section('content')
<section class="section"><div class="container"><div class="ops-page-head"><h1>مراجعة المرفقات</h1><p class="ops-help">اعرض المرفق وتحقق من وضوحه وصلاحيته، ثم سجل القرار وسبب الرفض أو الاستبدال عند الحاجة.</p></div>
  <p>هذه المراجعة تخص المرفق فقط، ولا تعتمد الطلب أو تصدر وثيقة.</p>
  @if(session('status'))<div class="alert alert-success" role="status">{{ session('status') }}</div>@endif
  @if($errors->any())<div class="alert alert-danger" role="alert"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
  <form method="GET" class="ops-toolbar card ops-card" style="padding:1.25rem"><label for="review-status" style="display:block;margin-block:1rem .4rem">الحالة</label><select id="review-status" name="review_status" class="form-control"><option value="">الكل</option>@foreach(['pending'=>'بانتظار المراجعة','uploaded'=>'مرفوع','approved'=>'مقبول','rejected'=>'مرفوض','replacement_required'=>'يحتاج استبدال'] as $state=>$label)<option value="{{ $state }}" @selected(request('review_status')===$state)>{{ $label }}</option>@endforeach</select><div style="margin-block-start:1rem"><button class="btn btn-outline">عرض</button></div> <a class="btn btn-outline" href="{{ url()->current() }}">مسح التصفية</a></form>
  @foreach($attachments as $attachment)
    <section class="card ops-card mb-3" style="padding:1rem">
      <h2>{{ $attachment->citizen->name }} — {{ $attachment->service->name }}</h2>
      <p>{{ $attachment->application ? 'الطلب: '.$attachment->application->reference : 'مرفق قبل تقديم الطلب' }}</p>
      @include('components.attachment-list', ['attachments' => [$attachment]])
      @can('review', $attachment)
      <form method="POST" action="{{ route('employee.attachments.review', $attachment) }}">
        @csrf @method('PATCH')
        <input type="hidden" name="expected_version" value="{{ $attachment->version_token }}">
        <label for="decision-{{ $attachment->id }}" style="display:block;margin-block:1rem .4rem">نتيجة مراجعة المرفق</label><select class="form-control" name="review_status" id="decision-{{ $attachment->id }}" required><option value="">اختر</option><option value="approved">مقبول</option><option value="rejected">مرفوض</option><option value="replacement_required">يحتاج إلى استبدال</option></select>
        <label for="reason-{{ $attachment->id }}" style="display:block;margin-block:1rem .4rem">سبب الرفض أو الاستبدال (يظهر للمواطن، دون معلومات سرية)</label><textarea class="form-control" name="public_reason" id="reason-{{ $attachment->id }}" maxlength="500"></textarea>
        <label for="expiry-{{ $attachment->id }}" style="display:block;margin-block:1rem .4rem">تاريخ الانتهاء الموثق من المستند (عند توفره أو اشتراطه)</label><input class="form-control" type="date" name="expires_at" id="expiry-{{ $attachment->id }}">
        <div style="margin-block-start:1rem"><button class="btn btn-primary" type="submit">حفظ مراجعة المرفق</button></div>
      </form>
      @endcan
    </section>
  @endforeach
  @if($attachments->isEmpty())<p>لا توجد مرفقات بهذه الحالة.</p>@endif
  {{ $attachments->links() }}
</div></section>
@endsection
