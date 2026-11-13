<?php

namespace App\Http\Controllers;

use App\Models\RequestCharge;
use App\Services\Identity\IdentityJourney;
use App\Services\Postal\PostalCollections;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PostalCollectionController extends Controller
{
    public function show(Request $request)
    {
        $data = $request->validate(['invoice' => ['nullable', 'string', 'max:50']]);
        $charge = empty($data['invoice']) ? null : RequestCharge::where('invoice_number', $data['invoice'])->first();
        if ($charge && ! IdentityJourney::applies($charge->application)) {
            $charge = null;
        }
        $receipt = $charge?->postalCollection;

        return response()->view('postal.collection', compact('charge', 'receipt'))->header('Cache-Control', 'no-store, private');
    }

    public function collect(Request $request, PostalCollections $collections)
    {
        $data = $request->validate(['invoice' => ['required', 'string', 'max:50'], 'operation_token' => ['required', 'uuid'], 'cash_received' => ['accepted'], 'amount' => ['missing']]);
        $charge = RequestCharge::where('invoice_number', $data['invoice'])->firstOrFail();
        $collections->collect(Auth::guard('postal')->user(), $charge, $data);

        return redirect()->route('postal.collections.show', ['invoice' => $charge->invoice_number])->with('status', 'تم تأكيد التحصيل. عند انقطاع الاستجابة ابحث عن الفاتورة للتحقق من السند قبل استلام النقد مجددًا.');
    }
}
