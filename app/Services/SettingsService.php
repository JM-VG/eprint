<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class SettingsService
{
    /**
     * Editable settings and their defaults (matches SettingSeeder).
     *
     * @var array<string, ?string>
     */
    public const DEFAULTS = [
        'commission_rate' => '0',
        'gcash_name' => '',
        'gcash_number' => '',
        'gcash_qr_path' => null,
        'retention_days' => '7',
        'student_id_pattern' => StudentRegistrationService::DEFAULT_PATTERN,
    ];

    /**
     * @return array<string, ?string>
     */
    public function all(): array
    {
        return collect(self::DEFAULTS)
            ->map(fn (?string $default, string $key) => Setting::read($key, $default))
            ->all();
    }

    /**
     * Save settings through the model so the settings cache is flushed.
     * A new GCash QR image replaces (and deletes) the previous one on the public disk.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(array $data, ?UploadedFile $gcashQr = null): void
    {
        foreach (['commission_rate', 'gcash_name', 'gcash_number', 'retention_days', 'student_id_pattern'] as $key) {
            Setting::updateOrCreate(['key' => $key], ['value' => (string) ($data[$key] ?? '')]);
        }

        if ($gcashQr) {
            $old = Setting::read('gcash_qr_path');
            $path = $gcashQr->store('gcash', 'public');

            Setting::updateOrCreate(['key' => 'gcash_qr_path'], ['value' => $path]);

            if ($old && $old !== $path) {
                Storage::disk('public')->delete($old);
            }
        }
    }

    public function gcashQrUrl(): ?string
    {
        $path = Setting::read('gcash_qr_path');

        return $path ? Storage::disk('public')->url($path) : null;
    }
}
