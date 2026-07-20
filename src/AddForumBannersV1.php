<?php

namespace LinkRobins\DiscussionBanners;

use Flarum\Settings\SettingsRepositoryInterface;

/**
 * Flarum 1.x: serialize the viewer's banners onto the forum payload via the
 * ApiSerializer extender. Parameters are intentionally untyped: the 1.x
 * serializer classes don't exist on a 2.x install, and this class must stay
 * loadable (for autoload dumps and static analysis) on both majors.
 */
final class AddForumBannersV1
{
    public function __construct(
        protected SettingsRepositoryInterface $settings,
    ) {
    }

    /**
     * @param mixed $serializer \Flarum\Api\Serializer\ForumSerializer at runtime
     * @param mixed $model
     * @param array<string, mixed> $attributes
     * @return array<string, mixed>
     */
    public function __invoke($serializer, $model, array $attributes): array
    {
        // Fail closed: degrade to "no banners" rather than break the forum
        // payload that every page load depends on.
        try {
            $attributes['linkrobinsDiscussionBanners'] = BannerSettings::visibleTo(
                $this->settings,
                $serializer->getActor()->isGuest()
            );
        } catch (\Throwable $e) {
            $attributes['linkrobinsDiscussionBanners'] = [];
        }

        return $attributes;
    }
}
