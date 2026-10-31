<?php

namespace App\Services\Team;

use App\Jobs\SendPasswordReset;
use App\Models\AdminUser;
use App\Notifications\TransactionalMessages;
use App\Support\AdminCapability;
use Illuminate\Support\Facades\Password;

/**
 * Inviting someone to the dashboard. The Super Admin adds them by name, email
 * and role; they get an email with a link to choose their own password, so
 * nobody else ever knows it. Until they do, the account has an unguessable
 * password and can't sign in.
 *
 * The link is a password-broker token (`admin_invites`, a week long) and
 * lands on the admin's /reset-password page, which knows it's an invitation
 * from `invite=1`.
 */
final class AdminInvites
{
    public function send(AdminUser $member, AdminUser $by): void
    {
        $token = Password::broker('admin_invites')->createToken($member);
        $days = intdiv((int) config('auth.passwords.admin_invites.expire', 10080), 60 * 24);

        $url = config('app.admin_url').'/reset-password?'.http_build_query([
            'token' => $token,
            'email' => $member->email,
            'invite' => 1,
        ]);

        $member->forceFill(['invited_at' => now()])->save();

        SendPasswordReset::dispatch($member, TransactionalMessages::adminInvite(
            $url,
            $by->name,
            AdminCapability::roleLabel($member->role),
            $days,
        ));
    }
}
