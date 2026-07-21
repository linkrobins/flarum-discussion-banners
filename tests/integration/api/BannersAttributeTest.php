<?php

/*
 * This file is part of linkrobins/discussion-banners.
 *
 * For detailed copyright and license information, please view the
 * LICENSE file that was distributed with this source code.
 */

namespace LinkRobins\DiscussionBanners\Tests\integration\api;

use Carbon\Carbon;
use Flarum\Testing\integration\RetrievesAuthorizedUsers;
use Flarum\Testing\integration\TestCase;
use PHPUnit\Framework\Attributes\Test;

class BannersAttributeTest extends TestCase
{
    use RetrievesAuthorizedUsers;

    public function setUp(): void
    {
        parent::setUp();

        $this->extension('linkrobins-discussion-banners');

        $this->prepareDatabase([
            'users' => [
                $this->normalUser(), // id 2
            ],
            'discussions' => [
                ['id' => 1, 'title' => 'First', 'created_at' => Carbon::now()->toDateTimeString(), 'user_id' => 1, 'first_post_id' => 1, 'comment_count' => 1, 'slug' => 'first', 'is_private' => 0],
                ['id' => 2, 'title' => 'Second', 'created_at' => Carbon::now()->toDateTimeString(), 'user_id' => 1, 'first_post_id' => 2, 'comment_count' => 1, 'slug' => 'second', 'is_private' => 0],
            ],
            'posts' => [
                ['id' => 1, 'discussion_id' => 1, 'number' => 1, 'created_at' => Carbon::now()->toDateTimeString(), 'user_id' => 1, 'type' => 'comment', 'content' => '<t><p>one</p></t>', 'is_private' => 0],
                ['id' => 2, 'discussion_id' => 2, 'number' => 1, 'created_at' => Carbon::now()->toDateTimeString(), 'user_id' => 1, 'type' => 'comment', 'content' => '<t><p>two</p></t>', 'is_private' => 0],
            ],
        ]);

        // Settings must go through the setting() helper (not prepareDatabase):
        // the settings repository is memory-cached at boot, before database
        // seeding runs.
        $this->setting('linkrobins-discussion-banners.banners', json_encode([
            ['id' => 'everywhere', 'enabled' => true, 'placement' => 'top', 'content' => '<p>Hello</p>', 'scope' => 'all'],
            ['id' => 'firstonly', 'enabled' => true, 'placement' => 'bottom', 'content' => '<p>Only here</p>', 'scope' => 'only', 'discussions' => [['id' => 1, 'title' => 'First']]],
            ['id' => 'members', 'enabled' => true, 'placement' => 'top', 'content' => '<p>Members</p>', 'scope' => 'all', 'visibility' => 'members'],
            ['id' => 'off', 'enabled' => false, 'placement' => 'top', 'content' => '<p>Disabled</p>', 'scope' => 'all'],
            ['id' => 'tagged', 'enabled' => true, 'placement' => 'top', 'content' => '<p>Tagged</p>', 'scope' => 'only', 'tags' => [['id' => 3, 'name' => 'News']]],
        ]));
    }

    /**
     * @return list<string>
     */
    private function bannerIds(int $discussionId, ?int $actorId): array
    {
        $options = $actorId ? ['authenticatedAs' => $actorId] : [];
        $response = $this->send($this->request('GET', '/api/discussions/'.$discussionId, $options));

        $this->assertEquals(200, $response->getStatusCode());

        $attributes = json_decode($response->getBody()->getContents(), true)['data']['attributes'];
        $this->assertArrayHasKey('linkrobinsDiscussionBanners', $attributes);

        return array_column($attributes['linkrobinsDiscussionBanners'], 'id');
    }

    #[Test]
    public function a_banner_targeted_at_a_discussion_is_only_sent_for_that_discussion(): void
    {
        $this->assertSame(['everywhere', 'firstonly'], $this->bannerIds(1, null));
        $this->assertSame(['everywhere'], $this->bannerIds(2, null));
    }

    #[Test]
    public function members_only_banners_are_hidden_from_guests_and_shown_to_members(): void
    {
        $this->assertNotContains('members', $this->bannerIds(1, null));
        $this->assertContains('members', $this->bannerIds(1, 2));
    }

    #[Test]
    public function disabled_banners_are_never_sent(): void
    {
        $this->assertNotContains('off', $this->bannerIds(1, 1));
    }

    #[Test]
    public function tag_targeting_matches_nothing_when_tags_are_not_installed(): void
    {
        // The tag lookup has to degrade quietly on a forum without
        // flarum/tags rather than error the discussion payload.
        $this->assertNotContains('tagged', $this->bannerIds(1, 1));
    }

    #[Test]
    public function the_discussion_list_does_not_carry_banner_html(): void
    {
        // Banners only render inside a discussion, so shipping them on every
        // row of the index would be pure payload.
        $response = $this->send($this->request('GET', '/api/discussions', []));

        $this->assertEquals(200, $response->getStatusCode());

        $body = json_decode($response->getBody()->getContents(), true);

        foreach ($body['data'] as $discussion) {
            $this->assertArrayNotHasKey('linkrobinsDiscussionBanners', $discussion['attributes']);
        }

        $this->assertStringNotContainsString('Hello', json_encode($body));
    }

    #[Test]
    public function banners_configured_on_1_0_still_serialize_before_the_migration_runs(): void
    {
        $this->setting('linkrobins-discussion-banners.banners', '');
        $this->setting('linkrobins-discussion-banners.top_enabled', '1');
        $this->setting('linkrobins-discussion-banners.top_content', '<p>Legacy</p>');

        $this->assertSame(['top'], $this->bannerIds(1, null));
    }
}
