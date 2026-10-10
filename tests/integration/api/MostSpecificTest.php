<?php

/*
 * This file is part of linkrobins/discussion-banners.
 *
 * For detailed copyright and license information, please view the
 * LICENSE file that was distributed with this source code.
 */

namespace LinkRobins\DiscussionBanners\Tests\integration\api;

use Carbon\Carbon;
use Flarum\Testing\integration\TestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * "Show only the most specific banner", against real tags: a discussion in a
 * subtag also carries the parent tag, and the subtag's banner should win.
 */
class MostSpecificTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();

        $this->extension('flarum-tags', 'linkrobins-discussion-banners');

        $now = Carbon::now()->toDateTimeString();

        $this->prepareDatabase([
            'tags' => [
                ['id' => 1, 'name' => 'Parent', 'slug' => 'parent', 'position' => 0],
                ['id' => 2, 'name' => 'Child', 'slug' => 'child', 'parent_id' => 1],
            ],
            'discussions' => [
                ['id' => 1, 'title' => 'In the child tag', 'created_at' => $now, 'user_id' => 1, 'first_post_id' => 1, 'comment_count' => 1, 'slug' => 'child-tag', 'is_private' => 0],
                ['id' => 2, 'title' => 'In the parent only', 'created_at' => $now, 'user_id' => 1, 'first_post_id' => 2, 'comment_count' => 1, 'slug' => 'parent-tag', 'is_private' => 0],
            ],
            'posts' => [
                ['id' => 1, 'discussion_id' => 1, 'number' => 1, 'created_at' => $now, 'user_id' => 1, 'type' => 'comment', 'content' => '<t><p>one</p></t>', 'is_private' => 0],
                ['id' => 2, 'discussion_id' => 2, 'number' => 1, 'created_at' => $now, 'user_id' => 1, 'type' => 'comment', 'content' => '<t><p>two</p></t>', 'is_private' => 0],
            ],
            'discussion_tag' => [
                ['discussion_id' => 1, 'tag_id' => 1],
                ['discussion_id' => 1, 'tag_id' => 2],
                ['discussion_id' => 2, 'tag_id' => 1],
            ],
        ]);

        $this->setting('linkrobins-discussion-banners.banners', json_encode([
            ['id' => 'everywhere', 'enabled' => true, 'placement' => 'top', 'content' => '<p>All</p>', 'scope' => 'all'],
            ['id' => 'parent', 'enabled' => true, 'placement' => 'top', 'content' => '<p>Parent</p>', 'scope' => 'only', 'tags' => [['id' => 1, 'name' => 'Parent']]],
            ['id' => 'child', 'enabled' => true, 'placement' => 'top', 'content' => '<p>Child</p>', 'scope' => 'only', 'tags' => [['id' => 2, 'name' => 'Child']]],
            ['id' => 'first', 'enabled' => true, 'placement' => 'first', 'content' => '<p>After the first post</p>', 'scope' => 'all'],
        ]));
    }

    /**
     * @return list<string>
     */
    private function bannerIds(int $discussionId): array
    {
        $response = $this->send($this->request('GET', '/api/discussions/'.$discussionId));

        $this->assertEquals(200, $response->getStatusCode());

        $attributes = json_decode($response->getBody()->getContents(), true)['data']['attributes'];

        return array_column($attributes['linkrobinsDiscussionBanners'], 'id');
    }

    #[Test]
    public function every_matching_banner_shows_by_default(): void
    {
        $this->assertSame(['everywhere', 'parent', 'child', 'first'], $this->bannerIds(1));
    }

    #[Test]
    public function the_subtag_banner_wins_when_most_specific_is_on(): void
    {
        $this->setting('linkrobins-discussion-banners.most_specific', '1');

        $this->assertSame(['child', 'first'], $this->bannerIds(1));
    }

    #[Test]
    public function the_parent_tag_banner_wins_where_there_is_no_subtag(): void
    {
        $this->setting('linkrobins-discussion-banners.most_specific', '1');

        $this->assertSame(['parent', 'first'], $this->bannerIds(2));
    }
}
