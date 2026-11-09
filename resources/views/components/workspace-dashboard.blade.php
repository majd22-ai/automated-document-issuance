@props(['dashboardRole'])
<section class="ops-section workspace-dashboard" data-workspace-dashboard="{{ $dashboardRole }}">
    <div class="container">{{ $slot }}</div>
</section>
