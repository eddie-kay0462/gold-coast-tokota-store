<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A local mirror of a template registered with Meta. Synced, never authored here. */
class WhatsAppTemplate extends Model
{
    protected $table = 'whatsapp_templates';

    protected $fillable = ['meta_id', 'name', 'language', 'category', 'status', 'components', 'rejection_reason', 'synced_at'];

    protected $casts = [
        'components' => 'array',
        'synced_at' => 'datetime',
    ];

    /** The BODY component's text, with its {{1}} placeholders. */
    public function bodyText(): string
    {
        foreach ($this->components ?? [] as $component) {
            if (strtoupper($component['type'] ?? '') === 'BODY') {
                return (string) ($component['text'] ?? '');
            }
        }

        return '';
    }

    /** @return list<string> the placeholders in the body, e.g. ['{{1}}', '{{2}}'] */
    public function variables(): array
    {
        preg_match_all('/\{\{\s*[\w.]+\s*\}\}/', $this->bodyText(), $matches);

        return array_values(array_unique($matches[0]));
    }
}
