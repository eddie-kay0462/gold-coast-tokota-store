<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MediaAssetResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'filename' => $this->filename,
            // Derived from the disk, not stored — see MediaAsset::getUrlAttribute.
            'url' => $this->url,
            'mime_type' => $this->mime_type,
            'size_bytes' => $this->size_bytes,
            'width' => $this->width,
            'height' => $this->height,
            'alt_text' => $this->alt_text ?? '',
            'uploaded_by_name' => $this->uploadedBy?->name,
            'uploaded_at' => $this->created_at,
        ];
    }
}
