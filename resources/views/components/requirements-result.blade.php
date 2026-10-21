<div class="card" style="padding:1.5rem;margin-top:1rem;overflow-x:auto;">
  <h2>نتيجة التحقق من المتطلبات</h2>
  <p role="status">{{ $requirementsResult['eligible'] ? 'يمكن المتابعة وفق المتطلبات المسجلة وقت الفحص.' : 'لا يمكن المتابعة حتى استيفاء المتطلبات الإلزامية أدناه.' }}</p>
  <p>هذه نتيجة وقتية يعاد فحصها عند الإرسال أو القبول؛ لا تمثل اعتمادًا حكوميًا أو إصدار وثيقة.</p>
  <table class="table" style="width:100%;text-align:start;">
    <thead><tr><th>المتطلب</th><th>النتيجة</th><th>التوضيح</th><th>جهة الاستكمال</th></tr></thead>
    <tbody>
      @foreach($requirementsResult['checks'] as $check)
        <tr>
          <td>{{ $check['label'] }}</td><td>{{ $check['state_label'] }}</td><td>{{ $check['message'] }}
            @foreach(['uploaded' => 'مرفوع', 'type_matches' => 'النوع مناسب', 'verified' => 'تم التحقق من المراجعة'] as $flag => $label)
              @if(array_key_exists($flag, $check['details'] ?? []))<br><small>{{ $label }}: {{ $check['details'][$flag] === null ? 'تعذّر التحقق' : ($check['details'][$flag] ? 'نعم' : 'لا') }}</small>@endif
            @endforeach
            @if(isset($check['details']['validity']))<br><small>صلاحية السجل: {{ ['expired' => 'منتهية', 'current' => 'سارية زمنيًا', 'incomplete' => 'بيانات غير مكتملة', 'invalid_dates' => 'تواريخ غير متسقة'][$check['details']['validity']] ?? 'تعذّر التحقق' }}</small>@endif
          </td><td>{{ ['citizen' => 'المواطن', 'staff' => 'الموظف المختص', 'external' => 'مصدر معتمد / الجهة المختصة'][$check['actor']] ?? 'الجهة المختصة' }}</td>
        </tr>
      @endforeach
    </tbody>
  </table>
  <p><small>وقت الفحص: {{ $requirementsResult['checked_at'] }}</small></p>
</div>
