<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'password', 'phone', 'avatar_media_id', 'whatsapp_jid', 'is_whatsapp_business', 'business_data', 'country_code'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    /**
     * Get the media items uploaded by this user.
     */
    public function media(): HasMany
    {
        return $this->hasMany(Media::class);
    }

    /**
     * Get the user's avatar.
     */
    public function avatar(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'avatar_media_id');
    }

    /**
     * Get the avatar URL if available, or a generated default avatar.
     */
    public function getAvatarUrlAttribute(): ?string
    {
        if (! $this->relationLoaded('avatar')) {
            $this->load('avatar');
        }

        if ($this->avatar) {
            return $this->avatar->url();
        }

        return $this->defaultAvatarUrl();
    }

    /**
     * URL de avatar generado con iniciales (siempre disponible, sin dependencias externas).
     * Devuelve un data-URI SVG que el navegador puede renderizar directamente.
     */
    public function defaultAvatarUrl(): string
    {
        $name = trim((string) ($this->name ?? '?'));
        if ($name === '') {
            $name = '?';
        }
        $initials = $this->initialsFromName($name);
        $color = $this->colorFromName($name);

        $svg = sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><rect width="100" height="100" fill="%s"/><text x="50" y="50" font-family="system-ui,-apple-system,sans-serif" font-size="42" font-weight="600" fill="#ffffff" text-anchor="middle" dominant-baseline="central">%s</text></svg>',
            $color,
            $initials,
        );

        return 'data:image/svg+xml;utf8,'.rawurlencode($svg);
    }

    private function initialsFromName(string $name): string
    {
        $name = trim($name);
        if ($name === '') {
            return '?';
        }

        $parts = preg_split('/\s+/u', $name) ?: [];
        $first = mb_substr($parts[0] ?? '', 0, 1);
        $last = count($parts) > 1 ? mb_substr($parts[count($parts) - 1] ?? '', 0, 1) : '';

        return mb_strtoupper($first.$last);
    }

    /**
     * Detecta el código de país ISO-3166-alpha2 a partir del teléfono usando
     * libphonenumber. Devuelve null si el número no es válido o está vacío.
     *
     * Estrategia:
     *  - Si el número ya tiene '+' al inicio, lo parsea directamente.
     *  - Si no, agrega el '+' (asumiendo formato E.164 implícito) y reintenta.
     *  - Si libphonenumber no puede validar el número, devuelve null (no se
     *    adivina el país a partir de heurísticas).
     */
    public function detectCountryCode(): ?string
    {
        $phone = trim((string) ($this->phone ?? ''));
        if ($phone === '') {
            return null;
        }

        $cleaned = preg_replace('/[^\d+]/', '', $phone) ?? '';
        if ($cleaned === '') {
            return null;
        }

        $digits = ltrim($cleaned, '+');
        if (strlen($digits) < 7) {
            return null;
        }

        return $this->tryDetect('+'.$digits);
    }

    private function tryDetect(string $phone): ?string
    {
        try {
            $phoneInstance = phone($phone);
            if ($phoneInstance->isValid()) {
                $country = $phoneInstance->getCountry();

                return $country ? strtoupper($country) : null;
            }
        } catch (\Throwable) {
            return null;
        }

        return null;
    }

    private function colorFromName(string $name): string
    {
        $palette = [
            '#0ea5e9', '#6366f1', '#8b5cf6', '#ec4899', '#f43f5e',
            '#ef4444', '#f97316', '#f59e0b', '#10b981', '#14b8a6',
            '#06b6d4', '#3b82f6', '#a855f7', '#d946ef', '#84cc16',
        ];

        $hash = 0;
        $len = strlen($name);
        for ($i = 0; $i < $len; $i++) {
            $hash = (($hash << 5) - $hash) + ord($name[$i]);
            $hash |= 0;
        }
        $index = abs($hash) % count($palette);

        return $palette[$index];
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
            'is_whatsapp_business' => 'boolean',
            'business_data' => 'array',
        ];
    }
}
