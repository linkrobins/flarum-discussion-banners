<?php

namespace LinkRobins\DiscussionBanners;

/**
 * Resolves the tag ids of a discussion, for banners targeted at a tag.
 *
 * Deliberately does not touch flarum/tags classes or the `tags` relation: the
 * relation is registered dynamically by that extension (so it can't be checked
 * with method_exists) and doesn't exist at all when tags are disabled. Reading
 * the pivot table behaves the same on Flarum 1.x and 2.x, and simply finds
 * nothing when the extension isn't installed.
 *
 * Resolution is lazy (only banners with tag targeting ask for it) and memoized
 * per request, so a discussion payload costs at most one extra query, and none
 * at all if the loaded discussion already carries its tags.
 */
final class DiscussionTags
{
    private ?bool $available = null;

    /** @var array<int, list<int>> */
    private array $cache = [];

    /**
     * @param  mixed  $discussion  \Flarum\Discussion\Discussion
     * @return list<int>
     */
    public function idsFor($discussion): array
    {
        $id = (int) $discussion->id;

        if (isset($this->cache[$id])) {
            return $this->cache[$id];
        }

        return $this->cache[$id] = $this->resolve($discussion, $id);
    }

    /**
     * @param  mixed  $discussion
     * @return list<int>
     */
    private function resolve($discussion, int $id): array
    {
        try {
            // The show endpoint usually includes tags already.
            if ($discussion->relationLoaded('tags')) {
                return array_values(array_map('intval', $discussion->getRelation('tags')->pluck('id')->all()));
            }

            $connection = $discussion->getConnection();

            if ($this->available === null) {
                $this->available = $connection->getSchemaBuilder()->hasTable('discussion_tag');
            }

            if (! $this->available) {
                return [];
            }

            return array_values(array_map('intval', $connection->table('discussion_tag')
                ->where('discussion_id', $id)
                ->pluck('tag_id')
                ->all()));
        } catch (\Throwable $e) {
            return [];
        }
    }
}
