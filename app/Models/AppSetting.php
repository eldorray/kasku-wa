<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class AppSetting extends Model
{
    protected $fillable = [
        'key',
        'value',
    ];

    public static function getValue(string $key, ?string $default = null): ?string
    {
        return Cache::rememberForever("app_setting:{$key}", function () use ($key, $default) {
            return static::query()->where('key', $key)->value('value') ?? $default;
        });
    }

    public static function setValue(string $key, ?string $value): void
    {
        static::query()->updateOrCreate(['key' => $key], ['value' => $value]);
        Cache::forget("app_setting:{$key}");
    }

    public static function logoUrl(): ?string
    {
        $path = static::getValue('app_logo');

        return $path ? Storage::url($path) : null;
    }

    public static function faviconUrl(): string
    {
        $path = static::getValue('app_favicon');

        return $path ? Storage::url($path) : asset('favicon.ico');
    }
}