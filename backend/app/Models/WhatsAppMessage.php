<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsAppMessage extends Model
{
    /**
     * Delivery status only moves forward. A `delivered` webhook that arrives
     * after `read` (Meta does not promise order) must not undo it.
     */
    public const STATUS_RANK = ['draft' => 0, 'queued' => 1, 'sent' => 2, 'delivered' => 3, 'read' => 4];

    protected $table = 'whatsapp_messages';

    protected $fillable = [
        'conversation_id',
        'wamid',
        'direction',
        'kind',
        'body',
        'media_path',
        'media_mime',
        'media_filename',
        'template_name',
        'status',
        'error',
        'source',
        'provider',
        'sent_by_admin_id',
        'sent_at',
        'meta',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
        'meta' => 'array',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(WhatsAppConversation::class, 'conversation_id');
    }

    public function sentBy(): BelongsTo
    {
        return $this->belongsTo(AdminUser::class, 'sent_by_admin_id');
    }

    /** One line for the thread list. */
    public function preview(): string
    {
        $text = trim((string) $this->body);

        if ($text === '') {
            $text = match ($this->kind) {
                'image' => 'Photo',
                'document' => $this->media_filename ?: 'Document',
                'audio' => 'Voice message',
                'video' => 'Video',
                'sticker' => 'Sticker',
                'location' => 'Location',
                'template' => "Template: {$this->template_name}",
                default => 'Message',
            };
        }

        return mb_strimwidth($text, 0, 157, '…');
    }
}
