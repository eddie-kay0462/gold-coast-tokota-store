<?php

namespace App\Http\Requests\Admin;

use App\Support\MegaMenus;
use Illuminate\Foundation\Http\FormRequest;

/**
 * A menu's image tiles. Each slot either names an image, a caption and a link,
 * or has no image, which empties it (the storefront then shows the menu's
 * newest product there).
 */
class UpdateMegaMenuRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'slots' => ['present', 'array', 'max:'.MegaMenus::SLOTS],
            'slots.*.position' => ['required', 'integer', 'between:1,'.MegaMenus::SLOTS, 'distinct'],
            'slots.*.media_asset_id' => ['nullable', 'integer', 'exists:media_assets,id'],
            'slots.*.caption' => ['required_with:slots.*.media_asset_id', 'nullable', 'string', 'max:80'],
            // In-site only: a path on the storefront, never another site.
            'slots.*.link' => ['required_with:slots.*.media_asset_id', 'nullable', 'string', 'max:255', 'regex:#^/(?!/)[^\s]*$#'],
        ];
    }

    public function messages(): array
    {
        return [
            'slots.*.caption.required_with' => 'Give the image a caption.',
            'slots.*.link.required_with' => 'Choose where the image links to.',
            'slots.*.link.regex' => 'The link must be a page on this site, starting with /, such as /shop?type=ahenema.',
            'slots.*.media_asset_id.exists' => 'That image is no longer in the media library.',
        ];
    }
}
