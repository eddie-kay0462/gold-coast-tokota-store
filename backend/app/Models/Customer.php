<?php

namespace App\Models;

use App\Jobs\SendPasswordReset;
use App\Notifications\TransactionalMessages;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class Customer extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'preferred_currency',
        'password',
    ];

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
