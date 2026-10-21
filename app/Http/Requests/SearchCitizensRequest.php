<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class SearchCitizensRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('searchCitizens', User::class) ?? false;
    }

    public function rules(): array
    {
        return ['search' => ['nullable', 'string', 'min:2', 'max:100'], 'page' => ['sometimes', 'integer', 'min:1']];
    }

    public function messages(): array
    {
        return ['search.string' => 'أدخل اسمًا أو رقمًا وطنيًا صحيحًا.', 'search.min' => 'أدخل حرفين على الأقل للبحث.', 'search.max' => 'نص البحث طويل جدًا.', 'page.integer' => 'رقم الصفحة غير صالح.', 'page.min' => 'رقم الصفحة غير صالح.'];
    }
}
