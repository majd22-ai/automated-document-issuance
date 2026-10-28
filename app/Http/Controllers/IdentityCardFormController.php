<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveIdentityCardForm;
use App\Models\IdentityCardForm;
use App\Services\IdentityCardForms;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class IdentityCardFormController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user()->is_active, 403);
        $forms = IdentityCardForm::where('user_id', $request->user()->id)->with('application')->latest('id')->paginate(15);

        return response()->view('citizen.identity-card.index', compact('forms'))->header('Cache-Control', 'no-store, private');
    }

    public function store(Request $request, IdentityCardForms $forms)
    {
        $form = $forms->create($request->user());

        return redirect()->route('citizen.identity-card.show', $form);
    }

    private function owner(Request $request, IdentityCardForm $form): void
    {
        abort_unless($request->user()->is_active && $request->user()->role === 'citizen' && $form->user_id === $request->user()->id, 403);
    }

    public function show(Request $request, IdentityCardForm $form, IdentityCardForms $forms)
    {
        $this->owner($request, $form);
        $request->validate(['step' => ['nullable', 'integer', 'between:1,7']]);
        $step = $form->submitted_at && ! $form->correctionIsOpen() ? 7 : min((int) $request->input('step', $form->current_step), $form->current_step);
        $form->load(['witnesses', 'photo', 'application']);
        $fields = config('identity_card.fields');
        $reviewToken = null;
        if ($step === 6) {
            $reviewToken = (string) Str::uuid();
            $reviews = array_filter($request->session()->get('identity_card_reviews', []), fn ($review) => $review['expires_at'] >= now()->timestamp);
            $reviews = array_slice($reviews, -9, null, true);
            $reviews[$reviewToken] = ['fingerprint' => $forms->reviewFingerprint($request->user(), $form), 'expires_at' => now()->addMinutes(30)->timestamp];
            $request->session()->put('identity_card_reviews', $reviews);
        }

        return response()->view('citizen.identity-card.wizard', compact('form', 'step', 'fields', 'reviewToken'))->header('Cache-Control', 'no-store, private');
    }

    public function save(SaveIdentityCardForm $request, IdentityCardForm $form, IdentityCardForms $forms)
    {
        $form = $forms->save($request->user(), $form, $request->validated(), $request->file('photo'));
        $step = $request->input('action') === 'next' ? min(6, (int) $request->input('step') + 1) : (int) $request->input('step');

        return redirect()->route('citizen.identity-card.show', ['form' => $form, 'step' => $step])->with('status', 'تم حفظ المسودة؛ يمكنك استكمالها لاحقًا.');
    }

    public function submit(Request $request, IdentityCardForm $form, IdentityCardForms $forms)
    {
        $this->owner($request, $form);
        $data = $request->validate(['version' => ['required', 'integer', 'min:1'], 'declaration' => ['accepted'], 'review_token' => ['required', 'uuid']], SaveIdentityCardForm::validationMessages());
        $form = $forms->submit($request->user(), $form, (int) $data['version'], $request->session()->get('identity_card_reviews.'.$data['review_token']));
        $request->session()->forget('identity_card_reviews.'.$data['review_token']);

        return redirect()->route('citizen.identity-card.show', $form);
    }

    public function photo(Request $request, IdentityCardForm $form)
    {
        if ($request->user()->role === 'citizen') {
            $this->owner($request, $form);
        } else {
            abort_unless($form->application, 404);
            Gate::authorize('viewPrintable', $form);
        }
        $photo = $form->photo;
        abort_unless($photo && $photo->intact(), 404);

        return Storage::disk('attachments')->response($photo->storage_path, 'identity-photo.'.($photo->mime_type === 'image/png' ? 'png' : 'jpg'), ['Cache-Control' => 'no-store, private', 'X-Content-Type-Options' => 'nosniff']);
    }

    public function employee(Request $request, IdentityCardForm $form)
    {
        abort_unless($form->application, 404);
        Gate::authorize('viewReview', $form->application);
        $form->load(['witnesses', 'photo', 'application']);

        return response()->view('employee.identity-card.show', compact('form'))->header('Cache-Control', 'no-store, private');
    }
}
