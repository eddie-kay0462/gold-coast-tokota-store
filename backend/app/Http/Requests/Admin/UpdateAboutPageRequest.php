<?php

namespace App\Http\Requests\Admin;

use App\Support\AboutPage;
use Illuminate\Foundation\Http\FormRequest;

/** The whole About page, as Content › Pages › About saves it. */
class UpdateAboutPageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return AboutPage::rules();
    }

    public function messages(): array
    {
        // Keyed by rule, so they apply to every field in the page.
        return [
            'url' => 'That link isn’t a full web address. Start it with https://.',
            'required' => 'Fill this in, or hide the section.',
            'exists' => 'That image is no longer in the media library.',
            'max' => 'That’s too long. Keep it under :max characters.',
            'content.watch.posts.*.id.regex' => 'That doesn’t look like a YouTube video ID or an Instagram reel code.',
        ];
    }
}
