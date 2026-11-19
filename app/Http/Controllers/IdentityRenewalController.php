<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveIdentityCardForm;
use App\Models\IdentityCardForm;
use App\Models\User;
use App\Services\Attachments\ManageAttachments;
use App\Services\Identity\IdentityRenewals;
use App\Services\IdentityCardForms;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class IdentityRenewalController extends Controller
{
    public function start(Request $request, IdentityRenewals $renewals)
    {
        return redirect()->route('citizen.identity-renewal.show', $renewals->create($request->user()));
    }

    private function owner(Request $request, IdentityCardForm $form): void
    {
        abort_unless($request->user()->is_active && $request->user()->role === 'citizen' && $form->user_id === $request->user()->id && $form->isRenewal(), 403);
    }

    public function show(Request $request, IdentityCardForm $form, IdentityRenewals $renewals, IdentityCardForms $forms)
    {
        $this->owner($request, $form);
        $documents = $request->user()->documents()->where('document_type_code', 'identity')->get();
        $copy = $renewals->cardCopy($form);
        $reviewToken = (string) Str::uuid();
        $request->session()->put('identity_card_reviews.'.$reviewToken, ['fingerprint' => $forms->reviewFingerprint($request->user(), $form), 'expires_at' => now()->addMinutes(30)->timestamp]);
        return response()->view('citizen.identity-card.renewal', compact('form', 'documents', 'copy', 'reviewToken'))->header('Cache-Control', 'no-store, private');
    }

    public function save(Request $request, IdentityCardForm $form, IdentityRenewals $renewals, IdentityCardForms $forms, ManageAttachments $attachments)
    {
        $this->owner($request, $form);
        $input = $request->validate(['version' => ['required', 'integer'], 'previous_document_id' => ['required', 'integer'],
            'data' => ['required', 'array:'.implode(',', array_keys(config('identity_card.fields.3')))],
            'photo' => ['nullable', 'image', 'max:'.config('identity_card.photo.max_kb'), 'dimensions:min_width=600,min_height=600'],
            'previous_card' => ['nullable', 'file', 'max:5120']], SaveIdentityCardForm::validationMessages());
        DB::transaction(function () use ($request, $form, $input, $renewals, $forms, $attachments) {
            User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            $form = IdentityCardForm::whereKey($form->id)->lockForUpdate()->firstOrFail();
            abort_unless((int) $input['version'] === $form->version && (! $form->submitted_at || $form->correctionIsOpen()), 409);
            if ($form->correctionIsOpen()) {
                abort_unless((int) $input['previous_document_id'] === (int) data_get($form->data, 'previous_document_id'), 409, 'البطاقة السابقة ثابتة بعد تقديم الطلب.');
                $allowed = $form->application->deficiencies()->where('status', '!=', 'resolved')->where('target_type', 'field')->pluck('target_key')->all();
                foreach ($input['data'] as $key => $value) {
                    if (! in_array($key, $allowed, true) && (string) $value !== (string) data_get($form->data, $key, '')) {
                        throw \Illuminate\Validation\ValidationException::withMessages(['data.'.$key => 'يمكن تعديل حقول العنوان المشار إليها في النواقص فقط.']);
                    }
                }
            }
            $data = array_replace($form->data ?? [], $input['data'], ['applicant_name' => $request->user()->name, 'previous_document_id' => (int) $input['previous_document_id']]);
            $errors = app(\App\Services\ResidencePhones::class)->errors($data);
            if ($errors) throw \Illuminate\Validation\ValidationException::withMessages($errors);
            $form->forceFill(['data' => $data, 'current_step' => max(4, $form->current_step)])->save();
            $renewals->assertPrevious($form);
            // Store each upload using the existing private, validated attachment pipeline.
            if ($request->hasFile('previous_card')) {
                $attachments->store($request->user(), $form->service, $form->application, 'renewal_previous_card', $request->file('previous_card'));
            }
            $form = $forms->save($request->user(), $form, ['version' => $form->version, 'step' => 4, 'action' => 'save'], $request->file('photo'));
            $form->forceFill(['current_step' => 7])->save();
        });
        return redirect()->route('citizen.identity-renewal.show', $form)->with('status', 'حُفظت المسودة. راجع بياناتك والمرفقات ثم أكد تقديم طلب التجديد.');
    }
}
