<?php

namespace LinkRobins\DiscussionBanners;

/**
 * Flarum 1.x: serialize the banners for THIS discussion and THIS viewer onto
 * the discussion payload via the ApiSerializer extender. Parameters are
 * intentionally untyped: the 1.x serializer classes don't exist on a 2.x
 * install, and this class must stay loadable (for autoload dumps and static
 * analysis) on both majors.
 *
 * 1.x uses one DiscussionSerializer for both the discussion list and a single
 * discussion, so the route name decides: banner HTML has no business riding
 * along on every row of the discussion index. The forum's own server-rendered
 * page goes through the same API pipeline (Flarum\Api\Client), so the route
 * name is set there too and a first page load gets its banners.
 */
final class AddDiscussionBannersV1
{
    public function __construct(
        protected BannerSettings $banners,
        protected DiscussionTags $tags,
    ) {
    }

    /**
     * @param  mixed  $serializer  \Flarum\Api\Serializer\DiscussionSerializer at runtime
     * @param  mixed  $discussion
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public function __invoke($serializer, $discussion, array $attributes): array
    {
        // Fail closed: degrade to "no banners" rather than break a payload
        // every discussion view depends on.
        try {
            $request = $serializer->getRequest();

            if (! $request || $request->getAttribute('routeName') !== 'discussions.show') {
                return $attributes;
            }

            $attributes['linkrobinsDiscussionBanners'] = $this->banners->visibleIn(
                $serializer->getActor()->isGuest(),
                (int) $discussion->id,
                fn () => $this->tags->idsFor($discussion),
            );
        } catch (\Throwable $e) {
            $attributes['linkrobinsDiscussionBanners'] = [];
        }

        return $attributes;
    }
}
