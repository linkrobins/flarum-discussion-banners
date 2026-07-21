<?php

namespace LinkRobins\DiscussionBanners;

use Flarum\Api\Context;
use Flarum\Api\Schema;

/**
 * Flarum 2.x: serialize the banners for THIS discussion and THIS viewer onto
 * the discussion resource. Only instantiated on 2.x (extend.php branches on
 * the extender classes), so the Schema/Context imports never load on 1.x.
 *
 * Serializing per discussion rather than once on the forum keeps the targeting
 * server-side: a banner written for one discussion is never sent to someone
 * reading another. The field is hidden outside single-discussion responses so
 * discussion listings don't carry banner HTML they'd never render.
 */
final class AddDiscussionBannersV2
{
    public function __construct(
        protected BannerSettings $banners,
        protected DiscussionTags $tags,
    ) {
    }

    /**
     * @return array<Schema\Arr>
     */
    public function __invoke(): array
    {
        return [
            Schema\Arr::make('linkrobinsDiscussionBanners')
                ->visible(fn ($model, Context $context) => $context->showing())
                ->get(function ($discussion, Context $context): array {
                    // Fail closed: a throw here must degrade to "no banners",
                    // not break the discussion every reader loads.
                    try {
                        return $this->banners->visibleIn(
                            $context->getActor()->isGuest(),
                            (int) $discussion->id,
                            fn () => $this->tags->idsFor($discussion),
                        );
                    } catch (\Throwable $e) {
                        return [];
                    }
                }),
        ];
    }
}
