<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UploadMediaRequest extends FormRequest
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
        $isAudio = $this->input('collection') === 'audio';

        return [
            'collection' => ['required', 'in:library,audio'],
            'file' => [
                'required',
                'file',
                $isAudio
                    ? 'mimetypes:audio/mpeg,audio/mp3,audio/wav,audio/ogg,audio/x-m4a'
                    : 'image',
                $isAudio ? 'max:20480' : 'max:10240', // KB: 20MB audio / 10MB image
            ],
        ];
    }
}
