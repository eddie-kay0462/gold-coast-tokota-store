<?php

namespace App\Notifications;

/**
 * One transactional message, in both the forms it might be delivered in.
 *
 * Email and SMS carry the *same* news in very different budgets — a styled
 * receipt with an itemised table versus roughly 160 characters. Keeping both
 * on one object means a trigger is defined once, in one place, rather than as
 * two loosely-related classes that drift apart the first time someone edits
 * the copy in only one of them.
 *
 * `sms` is nullable: a message with nothing worth paying for a text about is
 * email-only, and the SMS channel skips it rather than sending something
 * pointless.
 */
final class NotificationMessage
{
    /** @param  array<string, mixed>  $data */
    public function __construct(
        public readonly string $key,
        public readonly string $subject,
        public readonly string $view,
        public readonly array $data = [],
        public readonly ?string $sms = null,
    ) {}
}
