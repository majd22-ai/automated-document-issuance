@props(['title', 'items'])
<section class="card ops-card workspace-quick-links" aria-label="{{ $title }}">
    <h2>{{ $title }}</h2>
    <div class="workspace-quick-links__grid">
        @foreach($items as $item)
        <a class="workspace-quick-link" href="{{ route($item['route']) }}">
            <strong>{{ $item['label'] }}</strong>
            <span>{{ $item['description'] }}</span>
        </a>
        @endforeach
    </div>
</section>
