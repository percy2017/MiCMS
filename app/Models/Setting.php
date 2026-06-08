<?php

namespace App\Models;

use Database\Factories\SettingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['key', 'value', 'type'])]
class Setting extends Model
{
    /** @use HasFactory<SettingFactory> */
    use HasFactory;

    public const TYPE_STRING = 'string';

    public const TYPE_TEXT = 'text';

    public const TYPE_INTEGER = 'integer';

    public const TYPE_BOOLEAN = 'boolean';

    public const TYPE_URL = 'url';

    /**
     * Get a single setting by key, returning the default if not set.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        $row = static::query()->where('key', $key)->first();

        if (! $row) {
            return $default;
        }

        return static::castValue($row->value, $row->type);
    }

    /**
     * Set a single setting, creating or updating the row.
     */
    public static function set(string $key, mixed $value, string $type = self::TYPE_STRING): void
    {
        static::query()->updateOrCreate(
            ['key' => $key],
            [
                'value' => $value === null ? null : (is_bool($value) ? ($value ? '1' : '0') : (string) $value),
                'type' => $type,
            ],
        );
    }

    /**
     * Get all settings as a key-value array.
     *
     * @return array<string, mixed>
     */
    public static function allKeyed(): array
    {
        return static::query()
            ->get()
            ->mapWithKeys(fn (self $row) => [$row->key => static::castValue($row->value, $row->type)])
            ->all();
    }

    /**
     * Get the site-wide settings that are exposed to the public layout.
     *
     * @return array<string, mixed>
     */
    public static function site(): array
    {
        $defaults = [
            'site_name' => config('app.name'),
            'site_tagline' => '',
            'site_logo' => null,
        ];

        $stored = static::query()
            ->whereIn('key', ['site_name', 'site_tagline', 'site_logo'])
            ->get()
            ->mapWithKeys(fn (self $row) => [$row->key => static::castValue($row->value, $row->type)]);

        return array_merge($defaults, $stored->all());
    }

    protected static function castValue(?string $value, string $type): mixed
    {
        if ($value === null) {
            return null;
        }

        return match ($type) {
            self::TYPE_INTEGER => (int) $value,
            self::TYPE_BOOLEAN => in_array($value, ['1', 'true', true], true),
            self::TYPE_URL, self::TYPE_STRING, self::TYPE_TEXT => $value,
            default => $value,
        };
    }
}
