<section class="section"><div class="container"><div class="section-header"><h2>وثائق ومتطلبات السجل المدني</h2></div><div class="grid grid-2">
@foreach(config('civil_registry_guides') as $key => $guide)
<a class="service-item card-hover" href="{{ route('services.civil-registry.guide', $key) }}"><div class="content"><h3>{{ $guide['title'] }}</h3><p>الوثائق المطلوبة وصور القيود ومدة الإنجاز والرسوم الواردة في بطاقة الخدمة.</p><span class="btn btn-outline btn-sm">عرض المتطلبات</span></div></a>
@endforeach
</div></div></section>
