<?php

use Illuminate\Database\Schema\Builder;
use LinkRobins\DiscussionBanners\BannerSettings;

/**
 * 1.0.x stored one banner per placement in its own settings keys. 1.1 stores a
 * list of banners, so an admin can add several and target each at particular
 * discussions or tags. This copies the old three into the list, once.
 *
 * The old keys are deliberately left in place: they are what a downgrade back
 * to 1.0.x would read, and BannerSettings only falls back to them while the
 * list is empty.
 */
return [
    'up' => function (Builder $schema) {
        $db = $schema->getConnection();

        $existing = $db->table('settings')->where('key', BannerSettings::SETTING)->value('value');

        // Already converted (or already configured on 1.1): never overwrite.
        if (is_string($existing) && ! in_array(trim($existing), ['', '[]'], true)) {
            return;
        }

        $values = $db->table('settings')
            ->where('key', 'like', BannerSettings::PREFIX.'%')
            ->pluck('value', 'key')
            ->all();

        $rules = BannerSettings::fromLegacySettings($values);

        if ($rules === []) {
            return;
        }

        $db->table('settings')->updateOrInsert(
            ['key' => BannerSettings::SETTING],
            ['value' => json_encode($rules)]
        );
    },

    'down' => function (Builder $schema) {
        // The 1.0.x keys were never removed, so there is nothing to restore.
        $schema->getConnection()->table('settings')->where('key', BannerSettings::SETTING)->delete();
    },
];
