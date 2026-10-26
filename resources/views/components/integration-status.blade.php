@can('viewVerification',$application)
@php
  $verificationStates=[];$invalidIntegrationRules=false;
  try {foreach(\App\Services\Integrations\IntegrationRules::KINDS as $kind=>$label){$state=app(\App\Services\Integrations\IntegrationManager::class)->status($application,$kind);if($state['provider']){$verificationStates[]=$state;}}}catch(\Illuminate\Validation\ValidationException){$invalidIntegrationRules=true;}
@endphp
@if($verificationStates || $invalidIntegrationRules)<section><h2>التحقق من المصادر الخارجية</h2>@if($invalidIntegrationRules)<p>قواعد التحقق تحتاج مراجعة الإدارة.</p>@endif
@foreach($verificationStates as $state)<p><strong>{{ $state['label'] }}</strong>: {{ $state['message'] }} — المصدر: {{ $state['provider'] }} — آخر محاولة: {{ $state['attempted_at']?->format('Y-m-d H:i')??'لم تنفذ' }}</p>@endforeach</section>@endif
@endcan
