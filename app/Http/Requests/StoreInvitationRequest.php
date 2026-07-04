<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInvitationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', \App\Models\Invitation::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'template_key' => ['required', 'string', Rule::exists('templates', 'key')->where('status', 'active')],
            'title' => ['nullable', 'string', 'max:120'],
            'slug' => ['nullable', 'string', 'max:120', 'regex:/^[a-z0-9\-]+$/', Rule::notIn(self::reservedSlugs())],
        ];
    }

    /**
     * @return list<string>
     */
    public static function reservedSlugs(): array
    {
        return ['admin', 'api', 'storage', 'build', 'up', 'login', 'logout', 'sanctum', 'broadcasting'];
    }
}
