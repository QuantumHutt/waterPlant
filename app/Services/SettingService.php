<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Setting;

class SettingService extends BaseService
{
    /** @return array<string, array<string, mixed>> */
    public function all(): array
    {
        return Setting::allGrouped();
    }

    /**
     * Persist a nested group => [key => value] payload in one transaction.
     *
     * @param array<string, array<string, mixed>> $groups
     */
    public function updateGroups(array $groups): void
    {
        $this->transaction(function () use ($groups): void {
            foreach ($groups as $group => $pairs) {
                foreach ($pairs as $key => $value) {
                    $type = match (true) {
                        is_bool($value) => 'bool',
                        is_int($value) => 'int',
                        is_array($value) => 'json',
                        default => 'string',
                    };
                    Setting::set($group, $key, $value, $type);
                }
            }
        });
    }
}
