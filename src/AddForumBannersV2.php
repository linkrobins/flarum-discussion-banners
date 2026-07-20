<?php

namespace LinkRobins\DiscussionBanners;

use Flarum\Api\Context;
use Flarum\Api\Schema;
use Flarum\Settings\SettingsRepositoryInterface;

/**
 * Flarum 2.x: serialize the viewer's banners onto the forum resource.
 * Only instantiated on 2.x (extend.php branches on the extender classes),
 * so the Schema/Context imports never load on 1.x.
 */
final class AddForumBannersV2
{
    public function __construct(
        protected SettingsRepositoryInterface $settings,
    ) {
    }

    /**
     * @return array<Schema\Arr>
     */
    public function __invoke(): array
    {
        return [
            Schema\Arr::make('linkrobinsDiscussionBanners')
                ->get(function ($model, Context $context): array {
                    // Fail closed: this ships on every forum response, so a
                    // throw here must degrade to "no banners", not a 500.
                    try {
                        return BannerSettings::visibleTo($this->settings, $context->getActor()->isGuest());
                    } catch (\Throwable $e) {
                        return [];
                    }
                }),
        ];
    }
}
