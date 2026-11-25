@props(['id', 'source', 'title', 'label', 'side' => false, 'thumbnail' => false])

@if($thumbnail)
    <img class="review-image-thumbnail" src="{{ $source }}" alt="{{ $title }}">
@endif
<button type="button" class="btn btn-outline" aria-haspopup="dialog" aria-controls="{{ $id }}"
        onclick="document.getElementById(this.getAttribute('aria-controls')).showModal()">{{ $label }}</button>
<dialog id="{{ $id }}" class="review-image-dialog{{ $side ? ' review-image-dialog-right' : '' }}" aria-labelledby="{{ $id }}-title">
    <header class="review-image-dialog-header">
        <h3 id="{{ $id }}-title">{{ $title }}</h3>
        <form method="dialog"><button class="btn btn-outline" autofocus>إغلاق</button></form>
    </header>
    <img src="{{ $source }}" alt="{{ $title }}" loading="lazy">
</dialog>
