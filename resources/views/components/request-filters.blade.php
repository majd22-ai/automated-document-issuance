<form method="GET" style="margin:1rem 0;">
  <div class="form-group"><label class="form-label" for="request-search">البحث بالرقم المرجعي الكامل</label><input class="form-control" id="request-search" name="search" value="{{ request('search') }}" maxlength="100"></div>
  <div class="form-group"><label class="form-label" for="request-status">حالة الطلب</label><select class="form-control" id="request-status" name="status"><option value="">جميع الحالات</option>@foreach(\App\Enums\ServiceRequestStatus::cases() as $status)<option value="{{ $status->value }}" @selected(request('status') === $status->value)>{{ $status->label() }}</option>@endforeach</select></div>
  <button type="submit" class="btn btn-outline">بحث وتصفية</button>
</form>
