<label>اسم المكتب التجريبي<input class="form-control" name="name" value="{{ $office?->name }}" maxlength="160" required></label>
<label>الرمز الفريد<input class="form-control" name="code" dir="ltr" value="{{ $office?->code }}" placeholder="SANA-004" pattern="SANA-[0-9]{3,6}" maxlength="30" required></label>
<label>المنطقة<input class="form-control" name="area" value="{{ $office?->area }}" maxlength="160" required></label>
<label>العنوان التجريبي<input class="form-control" name="address" value="{{ $office?->address }}" maxlength="500" required></label>
<label>الحالة<select class="form-control" name="status"><option value="active" @selected($office?->status !== 'inactive')>نشط</option><option value="inactive" @selected($office?->status === 'inactive')>معطل</option></select></label>
<p>جميع المكاتب المضافة هنا تجريبية. تعطيل المكتب يمنع دخول حساباته.</p>
