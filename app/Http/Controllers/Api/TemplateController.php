<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\TemplateResource;
use App\Models\Template;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TemplateController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return TemplateResource::collection(
            Template::where('status', 'active')->orderBy('name')->get()
        );
    }

    public function show(string $key): TemplateResource
    {
        $template = Template::where('key', $key)->where('status', 'active')->firstOrFail();

        return new TemplateResource($template);
    }
}
