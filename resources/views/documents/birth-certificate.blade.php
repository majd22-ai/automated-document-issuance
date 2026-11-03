<!doctype html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 24mm 20mm; }
        body { font-family: DejaVu Sans, sans-serif; color: #172b3a; line-height: 1.8; }
        .notice { border: 2px solid #a33; color: #922; padding: 10px; text-align: center; font-weight: bold; }
        h1, h2, p { text-align: center; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #789; padding: 9px; text-align: right; }
        th { width: 34%; background: #edf3f6; }
        .verify { text-align: center; margin-top: 24px; }
        .verify img { width: 120px; height: 120px; }
        .number { direction: ltr; text-align: center; }
    </style>
</head>
<body>
    <p class="notice">نموذج تجريبي غير رسمي — لا يمثل شهادة حكومية ولا يصلح لإثبات الهوية أو الحقوق القانونية.</p>
    <h1>نموذج شهادة ميلاد تجريبي</h1>
    <p>سجل داخلي للمنصة التعليمية؛ لا يوجد تكامل أو اعتماد حكومي.</p>
    <h2 class="number">{{ $number }}</h2>
    <table>
        <tr><th>اسم المولود</th><td>{{ $data['child_name'] }}</td></tr>
        <tr><th>الجنس</th><td>{{ $data['child_gender'] }}</td></tr>
        <tr><th>تاريخ الميلاد</th><td>{{ $data['birth_date'] }}</td></tr>
        <tr><th>مكان الميلاد</th><td>{{ $data['birth_place'] }}</td></tr>
        <tr><th>اسم الأب</th><td>{{ $data['father_name'] }}</td></tr>
        <tr><th>اسم الأم</th><td>{{ $data['mother_name'] }}</td></tr>
        <tr><th>رقم واقعة الميلاد</th><td>{{ $data['birth_record_number'] }}</td></tr>
        @if(filled($data['hospital_name'] ?? null))<tr><th>المستشفى أو المركز</th><td>{{ $data['hospital_name'] }}</td></tr>@endif
        <tr><th>تاريخ إنشاء النموذج</th><td>{{ $issuedAt->format('Y-m-d') }}</td></tr>
    </table>
    <div class="verify"><p>امسح الرمز للتحقق من سجل النموذج داخل المنصة فقط.</p><img src="{{ $qrCode }}" alt="رمز تحقق داخلي"><p>التحقق لا يثبت الصفة الرسمية.</p></div>
</body>
</html>
