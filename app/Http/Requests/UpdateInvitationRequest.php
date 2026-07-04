<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateInvitationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('invitation'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'string', 'max:120'],
            'slug' => ['sometimes', 'string', 'max:120', 'regex:/^[a-z0-9\-]+$/', Rule::notIn(StoreInvitationRequest::reservedSlugs())],
            'locale' => ['sometimes', 'string', 'in:vi,en'],
            'settings' => ['sometimes', 'array'],
            'theme' => ['sometimes', 'array'],
            'seo' => ['sometimes', 'array'],
        ];
    }
}
