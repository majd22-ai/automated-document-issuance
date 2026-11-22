@extends('layouts.public')
@section('title', 'ملفات المواطنين التجريبية')
@section('content')
<section class="ops-section" aria-labelledby="registry-title">
    <div class="container">
        <div class="ops-page-head">
            <h1 id="registry-title">ملفات المواطنين التجريبية</h1>
            <p>سجلات مدنية خيالية تشمل المواطنين المرتبطين بحساب والمواطنين الذين لا يملكون حسابًا.</p>
        </div>
        <form class="card ops-card" action="{{ route('admin.mock-civil-registry.index') }}" method="get" role="search">
            @if($errors->any())
                <p class="alert alert-danger" role="alert">تعذر تطبيق البحث. تحقق من عبارة البحث والفلاتر ورقم الصفحة.</p>
            @endif
            <div class="ops-grid">
                <label for="registry-q">الاسم أو الرقم الوطني التجريبي أو مرجع الأسرة
                    <input id="registry-q" class="form-control" type="search" name="q" maxlength="120" value="{{ $filters['q'] ?? '' }}">
                </label>
                <label for="registry-account">ارتباط الحساب
                    <select id="registry-account" class="form-control" name="account">
                        <option value="">الكل</option>
                        <option value="linked" @selected(($filters['account'] ?? '') === 'linked')>مرتبط</option>
                        <option value="unlinked" @selected(($filters['account'] ?? '') === 'unlinked')>غير مرتبط</option>
                    </select>
                </label>
                <label for="registry-record">حالة القيد
                    <select id="registry-record" class="form-control" name="record">
                        <option value="">الكل</option>
                        <option value="complete" @selected(($filters['record'] ?? '') === 'complete')>مكتمل</option>
                        <option value="incomplete" @selected(($filters['record'] ?? '') === 'incomplete')>يحتاج مراجعة</option>
                    </select>
                </label>
            </div>
            <p><button class="btn btn-primary" type="submit">بحث</button>
                <a class="btn btn-outline" href="{{ route('admin.mock-civil-registry.index') }}">مسح الفلاتر</a></p>
        </form>

        <div class="card ops-card">
            <p>عدد النتائج <strong>{{ $people->total() }}</strong></p>
            <div style="overflow-x:auto">
                <table class="table" aria-label="نتائج البحث في السجل التجريبي">
                    <thead><tr><th scope="col">الاسم</th><th scope="col">الميلاد</th><th scope="col">مرجع الأسرة</th><th scope="col">القيد</th><th scope="col">الحساب</th></tr></thead>
                    <tbody>
                    @forelse($people as $person)
                        <tr>
                            <th scope="row"><a href="{{ route('admin.mock-civil-registry.show', $person->id) }}">{{ $person->name }}</a></th>
                            <td><bdi>{{ $person->birth_date }}</bdi></td>
                            <td><bdi>{{ $person->family_reference ?? '—' }}</bdi></td>
                            <td>{{ $person->complete ? 'مكتمل' : 'يحتاج مراجعة' }}</td>
                            <td>{{ $person->linked ? 'مرتبط' : 'غير مرتبط' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5">لا توجد سجلات في هذه الصفحة.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            {{ $people->links() }}
        </div>
    </div>
</section>
@endsection
