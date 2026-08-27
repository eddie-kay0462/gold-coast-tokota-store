<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SiteSettingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'whatsapp_number' => $this->whatsapp_number,
            'whatsapp_default_message' => $this->whatsapp_default_message,
            // The auto-reply the business sends on WhatsApp. Owner-managed
            // here; the storefront stores it but does not render it.
            'whatsapp_greeting' => $this->whatsapp_greeting,
            'business_hours' => $this->business_hours,
            'contact_email' => $this->contact_email,
            'contact_phone' => $this->contact_phone,
            'instagram_url' => $this->instagram_url,
            'hero_headline' => $this->hero_headline,
            'hero_image' => $this->hero_image,
            'diy_turnaround_estimate' => $this->diy_turnaround_estimate,
            // Rotating announcement-bar messages, in display order.
            'announcements' => $this->announcements ?? [],
            'updated_at' => $this->updated_at,
        ];
    }
}
