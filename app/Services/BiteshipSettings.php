<?php

namespace App\Services;

use App\Models\AppSetting;

class BiteshipSettings
{
    public const GROUP = 'biteship';

    public const KEYS = [
        'api_key',
        'default_couriers',
        'origin_contact_name',
        'origin_contact_phone',
        'origin_address',
        'origin_postal_code',
        'origin_area_id',
        'webhook_header_key',
        'webhook_secret',
    ];

    public function all(): array
    {
        $settings = AppSetting::query()
            ->where('group', self::GROUP)
            ->pluck('value', 'key');

        return collect(self::KEYS)
            ->mapWithKeys(fn(string $key) => [$key => $settings[$key] ?? config("services.biteship.{$key}")])
            ->all();
    }

    public function get(string $key, mixed $default = null): mixed
    {
        if (! in_array($key, self::KEYS, true)) {
            return $default;
        }

        $value = AppSetting::query()
            ->where('group', self::GROUP)
            ->where('key', $key)
            ->value('value');

        return filled($value) ? $value : config("services.biteship.{$key}", $default);
    }

    public function save(array $values): void
    {
        foreach (self::KEYS as $key) {
            if (! array_key_exists($key, $values)) {
                continue;
            }

            AppSetting::query()->updateOrCreate(
                [
                    'group' => self::GROUP,
                    'key' => $key,
                ],
                [
                    'value' => blank($values[$key]) ? null : trim((string) $values[$key]),
                ],
            );
        }
    }
}
