<?php

namespace LinkRobins\DiscussionBanners;

use Flarum\Settings\SettingsRepositoryInterface;

/**
 * Reads the banner settings and returns the banners a given viewer should
 * see. Shared by the Flarum 2.x ForumResource field and the 1.x
 * ForumSerializer attribute (see extend.php), so the visibility rules are
 * identical on both majors and members-only content never reaches a guest's
 * payload (and vice versa).
 */
final class BannerSettings
{
    public const PREFIX = 'linkrobins-discussion-banners.';

    public const PLACEMENTS = ['top', 'bottom', 'stream'];

    public const DEFAULT_STREAM_EVERY = 8;

    /**
     * @return list<array{placement: string, label: string, contentHtml: string, every?: int}>
     */
    public static function visibleTo(SettingsRepositoryInterface $settings, bool $isGuest): array
    {
        $banners = [];

        foreach (self::PLACEMENTS as $placement) {
            $prefix = self::PREFIX.$placement;

            if (! (bool) $settings->get($prefix.'_enabled')) {
                continue;
            }

            $content = trim((string) $settings->get($prefix.'_content'));
            if ($content === '') {
                continue;
            }

            $visibility = (string) ($settings->get($prefix.'_visibility') ?: 'everyone');
            if (($visibility === 'guests' && ! $isGuest) || ($visibility === 'members' && $isGuest)) {
                continue;
            }

            $banner = [
                'placement' => $placement,
                'label' => (string) $settings->get($prefix.'_label'),
                'contentHtml' => $content,
            ];

            if ($placement === 'stream') {
                // Guard against nonsense cadence values: below 2 the stream
                // would be mostly banners.
                $every = (int) $settings->get(self::PREFIX.'stream_every');
                $banner['every'] = $every >= 2 ? $every : self::DEFAULT_STREAM_EVERY;
            }

            $banners[] = $banner;
        }

        return $banners;
    }
}
