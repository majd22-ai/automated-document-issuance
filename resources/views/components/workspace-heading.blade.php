@props(['title', 'description', 'roleLabel'])
<header class="ops-page-head workspace-heading" data-workspace-heading>
    <span class="ops-role">{{ $roleLabel }}</span>
    <h1>{{ $title }}</h1>
    <p>{{ $description }}</p>
    <div class="ops-toolbar" aria-label="روابط الحساب">
        @if(auth()->user()->role !== 'admin')
        <a class="btn btn-outline" href="{{ route(auth()->user()->role.'.notifications.index') }}">الإشعارات <span class="ops-status">{{ auth()->user()->unreadNotifications()->count() }} غير مقروءة</span></a>
        @endif
        <a class="btn btn-outline" href="{{ route('profile.edit') }}">الملف الشخصي</a>
    </div>
</header>
