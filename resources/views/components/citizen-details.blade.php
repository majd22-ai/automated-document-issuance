<div class="card" style="padding:1.5rem;">
  <h2>البيانات الأساسية للمواطن</h2>
  <p>بيانات الحساب المسجلة في المنصة؛ لا تمثل تحققًا مستقلًا من السجل المدني.</p>
  @if(session('citizen_data_error'))
    <div class="alert alert-danger" role="alert">{{ session('citizen_data_error') }}</div>
  @endif
  @if($missing)
    <div class="alert alert-danger" role="alert">بياناتك غير مكتملة. الحقول المطلوبة: {{ implode('، ', $missing) }}. راجع الجهة المختصة لتصحيح الرقم الوطني، ويمكن تحديث بيانات الاتصال من الملف الشخصي.</div>
  @else
    <p role="status">الحقول الأساسية المطلوبة مكتملة.</p>
  @endif
  <dl>
    <dt>الاسم الكامل</dt><dd>{{ filled($citizen->name) ? $citizen->name : 'غير مسجل' }}</dd>
    <dt>الرقم الوطني</dt><dd>{{ filled($citizen->username) ? $citizen->username : 'غير مسجل' }}</dd>
    <dt>البريد الإلكتروني</dt><dd>{{ filled($citizen->email) ? $citizen->email : 'غير مسجل' }}</dd>
    <dt>رقم الهاتف</dt><dd>{{ $citizen->phone ?? 'غير مسجل (اختياري)' }}</dd>
  </dl>
</div>
