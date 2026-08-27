<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreMediaAssetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'file' => [
                'required',
                'file',
                'max:8192', // 8MB — product photography, not video.
                // `mimes` checks the actual file contents, not the extension
                // or the client-supplied Content-Type. An allowlist of raster
                // formats, so nothing that a browser will execute can be
                // uploaded and then linked from a page.
                'mimes:jpg,jpeg,png,webp,avif',
                // Belt and braces: `dimensions` forces the file through an
                // image decoder, so a renamed script that survives the mime
                // check still fails here.
                'dimensions:min_width=64,min_height=64,max_width=6000,max_height=6000',
            ],
            'alt_text' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'file.mimes' => 'Upload a JPG, PNG, WebP or AVIF image.',
            'file.max' => 'Images must be 8MB or smaller.',
            'file.dimensions' => 'Images must be between 64 and 6000 pixels on each side.',
        ];
    }
}
