<?php

namespace App\Models;

use App\Jobs\SendPasswordReset;
use App\Notifications\TransactionalMessages;
use App\Support\PersonName;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class Customer extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'first_name',
        'last_name',
        // Accepted so older callers that only know a full name still work;
        // it is split into the two parts on save (see booted()).
        'name',
        'email',
        'phone',
        'preferred_currency',
        'password',
    ];

    /**
     * `name` is the full name, kept equal to `first_name` + `last_name` on
     * every save, so the many places that show a customer's whole name read
     * one column.
     *
     * Usually the parts are set and `name` follows. A `name` set on its own
     * (an older caller), or one that disagrees with the parts it was set
     * alongside, wins instead and is split into them.
     */
    protected static function booted(): void
    {
        static::saving(function (Customer $customer) {
            $joined = PersonName::join($customer->first_name, $customer->last_name);

            if ($customer->isDirty('name') && filled($customer->name) && $customer->name !== $joined) {
                [$customer->first_name, $customer->last_name] = PersonName::split((string) $customer->name);
                $customer->name = PersonName::join($customer->first_name, $customer->last_name);
            } elseif ($joined !== '') {
                $customer->name = $joined;
            }
        });
    }

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
        ];
    }

    /**
     * Overridden so the reset link points at the **storefront**, not at this
     * API.
     *
     * Laravel's default ResetPassword notification builds its URL from
     * `route('password.reset')` — a named web route a headless API has no
     * reason to define, and which would 404 for the customer if it did. The
     * page that accepts the token belongs to the Nuxt storefront, so the link
     * is assembled here and sent through the Feature 8 dispatcher like every
     * other customer email.
     *
     * The email is carried in the URL because Laravel's broker needs it
     * alongside the token to verify the reset — the token alone does not
     * identify the account.
     */
    public function sendPasswordResetNotification($token): void
    {
        $url = config('app.storefront_url')
            .'/account/reset-password?token='.$token
            .'&email='.urlencode($this->email);

        SendPasswordReset::dispatch($this, TransactionalMessages::passwordReset(
            $url,
            (int) config('auth.passwords.customers.expire', 60),
        ));
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }
}
