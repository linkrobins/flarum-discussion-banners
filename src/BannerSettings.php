<?php

namespace LinkRobins\DiscussionBanners;

use Flarum\Settings\SettingsRepositoryInterface;
use Illuminate\Contracts\Filesystem\Factory;

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

    public function __construct(
        protected SettingsRepositoryInterface $settings,
        protected Factory $filesystem,
    ) {
    }

    /**
     * @return list<array{placement: string, label: string, contentHtml: string, icon?: array{type: string, url?: string, emoji?: string}, every?: int}>
     */
    public function visibleTo(bool $isGuest): array
    {
        $banners = [];

        foreach (self::PLACEMENTS as $placement) {
            $prefix = self::PREFIX.$placement;

            if (! (bool) $this->settings->get($prefix.'_enabled')) {
                continue;
            }

            $content = trim((string) $this->settings->get($prefix.'_content'));
            if ($content === '') {
                continue;
            }

            $visibility = (string) ($this->settings->get($prefix.'_visibility') ?: 'everyone');
            if (($visibility === 'guests' && ! $isGuest) || ($visibility === 'members' && $isGuest)) {
                continue;
            }

            $banner = [
                'placement' => $placement,
                'label' => (string) $this->settings->get($prefix.'_label'),
                'contentHtml' => $content,
            ];

            if ($icon = $this->icon($prefix)) {
                $banner['icon'] = $icon;
            }

            if ($placement === 'stream') {
                // Guard against nonsense cadence values: below 2 the stream
                // would be mostly banners.
                $every = (int) $this->settings->get(self::PREFIX.'stream_every');
                $banner['every'] = $every >= 2 ? $every : self::DEFAULT_STREAM_EVERY;
            }

            $banners[] = $banner;
        }

        return $banners;
    }

    /**
     * @return array{type: string, url?: string, emoji?: string}|null
     */
    private function icon(string $prefix): ?array
    {
        $type = (string) $this->settings->get($prefix.'_icon_type');

        if ($type === 'emoji') {
            $emoji = trim((string) $this->settings->get($prefix.'_icon_emoji'));
            if ($emoji !== '') {
                // Enough room for ZWJ sequences and flags, but nothing essay-sized.
                return ['type' => 'emoji', 'emoji' => mb_substr($emoji, 0, 16)];
            }

            return null;
        }

        if ($type === 'image') {
            $path = (string) $this->settings->get($prefix.'_icon_path');
            if ($path !== '') {
                // The URL is resolved from the stored path at read time so it
                // survives base-URL changes and CDN'd asset disks.
                try {
                    return ['type' => 'image', 'url' => $this->filesystem->disk('flarum-assets')->url($path)];
                } catch (\Throwable $e) {
                    return null;
                }
            }
        }

        return null;
    }
}
