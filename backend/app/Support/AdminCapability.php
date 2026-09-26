<?php

namespace App\Support;

/**
 * Who can do what, as a capability table rather than scattered role checks.
 *
 * The role model had disagreed across three sources — the README says two
 * tiers, `admin_users.role` said two, the admin dashboard implements four.
 * `GOLD_COAST_TOKOTA.md` settles it: §17 names the real people and their
 * tiers, §18 defines the tiers themselves, and §22.14 makes the Super Admin /
 * Admin / Staff model binding. The one line that decides most of this table is
 * §18's limit on Admin: *"Cannot modify: system-level settings, payment
 * credentials"* — which is why payment keys, FX provider config and team
 * management are Super Admin only, and everything else operational is not.
 *
 * `intern` is not in the brand document. It is the fourth tier the business
 * asked for, already built into the dashboard as a time-boxed read-only
 * account (see `access_expires_at`), and the document does not contradict it —
 * so it is kept, below Staff, rather than silently dropped and breaking a
 * shipped screen.
 *
 * This is the server-side twin of `admin/utils/permissions.ts`. That one hides
 * buttons; this one is the boundary. **They must agree** — a capability added
 * there without being added here is a button that 403s, and one added here
 * without being added there is an action nobody can reach. Change both.
 */
final class AdminCapability
{
    /** @var list<string> */
    public const ALL = [
        // Commerce
        'orders.view',
        'orders.update_status',
        'orders.refund',
        'returns.view',
        'returns.resolve',
        'shipments.view',
        'shipments.update',
        'customers.view',
        'customers.export',

        // Catalogue
        'products.view',
        'products.write',
        'products.delete',
        'pricing.write',
        'inventory.view',
        'inventory.adjust',

        // Bookings
        'bookings.view',
        'bookings.update_status',
        'workshops.manage',
        'waitlist.promote',

        // Content
        'content.view',
        'content.write',
        'content.publish',
        'content.delete',
        'media.upload',
        'media.delete',

        // Messaging
        'inbox.view',
        'inbox.draft',
        'inbox.reply',
        'inbox.templates',

        // Platform
        'analytics.view',
        // Split from analytics.view deliberately: everyone needs the
        // operational dashboard, but §18 keeps money away from Staff, so the
        // revenue figures carry their own capability.
        'analytics.revenue',
        'settings.view',
        'settings.write',
        'settings.payments',
        'settings.fx',
        'team.view',
        'team.manage',
        'audit.view',
    ];

    public const ROLES = ['super_admin', 'admin', 'staff', 'intern'];

    /**
     * Admin is everything except system-level configuration — the exact line
     * §18 draws.
     *
     * @var list<string>
     */
    private const ADMIN_DENIED = ['settings.payments', 'settings.fx', 'team.manage'];

    /**
     * Staff: operational access only. §18 — "production updates, inventory
     * management, order fulfilment" — with no pricing, refunds or deletions.
     *
     * @var list<string>
     */
    private const STAFF = [
        'orders.view', 'orders.update_status',
        'returns.view',
        'shipments.view', 'shipments.update',
        'customers.view',
        'products.view',
        'inventory.view', 'inventory.adjust',
        'bookings.view', 'bookings.update_status',
        'workshops.manage', 'waitlist.promote',
        'content.view', 'content.write',
        'media.upload',
        'inbox.view', 'inbox.draft', 'inbox.reply',
        'analytics.view',
        'settings.view',
        'team.view',
    ];

    /**
     * Intern: read-only everywhere, plus drafting an inbox reply for someone
     * else to send.
     *
     * @var list<string>
     */
    private const INTERN = [
        'orders.view',
        'returns.view',
        'shipments.view',
        'customers.view',
        'products.view',
        'inventory.view',
        'bookings.view',
        'content.view',
        'inbox.view', 'inbox.draft',
        'analytics.view',
        'team.view',
    ];

    /** @return list<string> */
    public static function for(string $role): array
    {
        return match ($role) {
            'super_admin' => self::ALL,
            'admin' => array_values(array_diff(self::ALL, self::ADMIN_DENIED)),
            'staff' => self::STAFF,
            'intern' => self::INTERN,
            default => [],
        };
    }

    public static function allows(string $role, string $capability): bool
    {
        return in_array($capability, self::for($role), true);
    }

    /**
     * A human explanation for a blocked action.
     *
     * README Feature 9 requires staff to see "a clear, non-technical error
     * message (not a raw 403 JSON blob)", so this copy is written for the
     * person who hit the wall, not for the log. It mirrors `denialMessage()`
     * in the admin app so the same refusal reads the same whether the UI
     * caught it or the server did.
     */
    public static function denialMessage(string $capability, string $role): string
    {
        $specific = [
            'orders.refund' => 'Refunds can only be issued by an Admin.',
            'pricing.write' => 'Only an Admin can change prices.',
            'products.delete' => 'Only an Admin can delete products.',
            'settings.write' => 'Site settings are managed by an Admin.',
            'settings.payments' => 'Payment credentials are restricted to the Super Admin.',
            'settings.fx' => 'Currency and exchange-rate configuration is restricted to the Super Admin.',
            'team.manage' => 'Only the Super Admin can add or change team members.',
            'content.publish' => 'Publishing is restricted — you can save a draft and ask an Admin to publish it.',
            'inbox.reply' => 'You can draft a reply, but sending it needs a Staff or Admin account.',
            'analytics.revenue' => 'Revenue figures are visible to Admins only.',
        ];

        if (isset($specific[$capability])) {
            return $specific[$capability];
        }

        $label = self::roleLabel($role);

        return "Your {$label} account doesn't have access to this. Ask an Admin if you need it.";
    }

    public static function roleLabel(string $role): string
    {
        return match ($role) {
            'super_admin' => 'Super Admin',
            'admin' => 'Admin',
            'staff' => 'Staff',
            'intern' => 'Intern',
            default => 'Unknown',
        };
    }
}
