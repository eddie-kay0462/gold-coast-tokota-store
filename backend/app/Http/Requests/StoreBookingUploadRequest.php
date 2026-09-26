<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * A reference photo for a Design-Your-Own order (README Feature 7).
 *
 * **This is the only unauthenticated write-to-disk surface on the API**, so
 * the rules are tighter than the admin media library's rather than looser:
 * guest bookings are supported by design, so it cannot sit behind a login, and
 * everything that would normally be handled by "only staff can reach it" has
 * to be handled here instead.
 */
class StoreBookingUploadRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Guest bookings are supported (Feature 7), the same way guest
        // checkout is. The route's throttle is the control, not a session.
        return true;
    }

    public function rules(): array
    {
        return [
            'file' => [
                'required',
                'file',
                // Smaller than the media library's 8MB: this is a phone
                // snapshot of a sandal, not product photography, and a public
                // endpoint should accept the smallest thing that does the job.
                'max:5120',
                // Contents, not the extension or the client's Content-Type.
                // An allowlist of raster formats, so nothing a browser will
                // execute can be stored and then linked.
                'mimes:jpg,jpeg,png,webp,heic,heif',
                // Forces the file through an image decoder, so a renamed
                // script that somehow satisfied the mime check still fails.
                'dimensions:min_width=64,min_height=64,max_width=8000,max_height=8000',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'file.required' => 'Choose a photo to upload.',
            'file.mimes' => 'Upload a JPG, PNG, WebP or HEIC photo.',
            'file.max' => 'Photos must be 5MB or smaller.',
            'file.dimensions' => 'That file does not look like a photo.',
        ];
    }
}
