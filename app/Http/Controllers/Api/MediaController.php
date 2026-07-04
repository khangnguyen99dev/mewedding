<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UploadMediaRequest;
use App\Models\Invitation;
use Illuminate\Http\JsonResponse;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class MediaController extends Controller
{
    public function store(UploadMediaRequest $request, Invitation $invitation): JsonResponse
    {
        $collection = $request->input('collection');

        $media = $invitation
            ->addMediaFromRequest('file')
            ->toMediaCollection($collection);

        return response()->json(['data' => $this->transform($media, $collection)], 201);
    }

    public function destroy(Invitation $invitation, Media $media): JsonResponse
    {
        $this->authorize('update', $invitation);

        abort_unless($media->model_type === Invitation::class && $media->model_id === $invitation->id, 404);

        $media->delete();

        return response()->json(['message' => 'Đã xoá tệp.']);
    }

    /**
     * @return array<string, mixed>
     */
    protected function transform(Media $media, string $collection): array
    {
        if ($collection === Invitation::COLLECTION_AUDIO) {
            return ['id' => $media->id, 'name' => $media->file_name, 'url' => $media->getUrl()];
        }

        return [
            'id' => $media->id,
            'name' => $media->file_name,
            'thumb' => $media->hasGeneratedConversion('thumb') ? $media->getUrl('thumb') : $media->getUrl(),
            'web' => $media->hasGeneratedConversion('web') ? $media->getUrl('web') : $media->getUrl(),
            'full' => $media->getUrl(),
            'alt' => (string) $media->getCustomProperty('alt', ''),
        ];
    }
}
