@php
  $locationQuery = 'مجمع الإصدار الآلي الموحد، شارع خولان، صنعاء، اليمن';
  $mapQuery = rawurlencode($locationQuery);
@endphp
<section class="gov-location-section section" id="location" aria-labelledby="location-heading">
  <div class="gov-container">
    <div class="gov-location-heading">
      <h2 id="location-heading">موقعنا</h2>
      <p>مجمع الإصدار الآلي الموحد — شارع خولان، صنعاء</p>
    </div>
    <div class="gov-location-map">
      <iframe
        src="https://maps.google.com/maps?q={{ $mapQuery }}&amp;z=16&amp;hl=ar&amp;output=embed"
        title="موقع مجمع الإصدار الآلي الموحد في شارع خولان بصنعاء"
        width="760" height="340" loading="lazy"
        referrerpolicy="no-referrer-when-downgrade" allowfullscreen>
      </iframe>
    </div>
    <a class="gov-location-directions" href="https://www.google.com/maps/dir/?api=1&amp;destination={{ $mapQuery }}" target="_blank" rel="noopener noreferrer">
      <x-icon name="location" /> فتح الموقع والاتجاهات في خرائط Google <x-icon name="arrow-left" />
    </a>
  </div>
</section>
