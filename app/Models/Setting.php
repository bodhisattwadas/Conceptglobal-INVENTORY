<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['key', 'value'];

    /**
     * Get a setting value by key.
     */
    public static function get(string $key, ?string $default = null): ?string
    {
        $value = Cache::rememberForever("settings.{$key}", function () use ($key) {
            $setting = self::find($key);

            return $setting ? $setting->value : null;
        });

        return $value ?? $default;
    }

    /**
     * Set a setting value by key.
     */
    public static function set(string $key, ?string $value): void
    {
        self::updateOrCreate(
            ['key' => $key],
            ['value' => $value]
        );

        Cache::forget("settings.{$key}");
    }

    /**
     * Forget / remove a setting by key.
     */
    public static function forget(string $key): void
    {
        self::where('key', $key)->delete();

        Cache::forget("settings.{$key}");
    }
}
