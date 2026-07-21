<?php

namespace LinkRobins\DiscussionBanners;

use Flarum\Settings\Event\Saved;
use Illuminate\Contracts\Filesystem\Factory;

/**
 * Deletes uploaded icons no banner refers to any more, whenever the banners
 * are saved. Icons are uploaded before the banner that uses them is saved, so
 * without this an admin trying out images would leave files behind forever.
 *
 * The referenced paths are read from the value that was just saved rather than
 * from the settings repository, so this never depends on cache state. If that
 * value doesn't parse, nothing is deleted: losing an icon is worse than
 * keeping an orphan. Only our own directory is ever listed or deleted from,
 * because settings are writable by any admin through the API and a
 * hand-edited path must not be able to aim this at someone else's files.
 */
final class PruneIcons
{
    public function __construct(
        protected BannerSettings $banners,
        protected Factory $filesystem,
    ) {
    }

    public function handle(Saved $event): void
    {
        if (! array_key_exists(BannerSettings::SETTING, $event->settings)) {
            return;
        }

        $decoded = json_decode((string) $event->settings[BannerSettings::SETTING], true);

        if (! is_array($decoded)) {
            return;
        }

        try {
            $keep = array_flip($this->banners->iconPaths(BannerSettings::normalize($decoded)));
            $disk = $this->filesystem->disk('flarum-assets');

            foreach ($disk->files(rtrim(BannerSettings::ICON_DIR, '/')) as $file) {
                if (! isset($keep[$file])) {
                    $disk->delete($file);
                }
            }
        } catch (\Throwable $e) {
            // Orphaned files are not worth failing an admin's save over.
        }
    }
}
