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

/**
 * "Visible to" groups, end to end: what each kind of viewer actually receives
 * in the discussion payload.
 */
class GroupVisibilityTest extends TestCase
{
    use RetrievesAuthorizedUsers;

    public function setUp(): void
    {
        parent::setUp();

        $this->extension('flarum-tags', 'linkrobins-discussion-banners');

        $now = Carbon::now()->toDateTimeString();

        $this->prepareDatabase([
            'users' => [
                $this->normalUser(), // id 2, Members only
                ['id' => 3, 'username' => 'mod', 'email' => 'mod@machine.local', 'is_email_confirmed' => 1, 'password' => 'x'],
                ['id' => 4, 'username' => 'vip', 'email' => 'vip@machine.local', 'is_email_confirmed' => 1, 'password' => 'x'],
            ],
            'groups' => [
                ['id' => 5, 'name_singular' => 'VIP', 'name_plural' => 'VIPs'],
            ],
            'group_user' => [
                ['user_id' => 3, 'group_id' => 4],
                ['user_id' => 4, 'group_id' => 5],
            ],
            'tags' => [
                ['id' => 1, 'name' => 'News', 'slug' => 'news', 'position' => 0],
            ],
            'discussions' => [
                ['id' => 1, 'title' => 'Tagged', 'created_at' => $now, 'user_id' => 1, 'first_post_id' => 1, 'comment_count' => 1, 'slug' => 'tagged', 'is_private' => 0],
            ],
            'posts' => [
                ['id' => 1, 'discussion_id' => 1, 'number' => 1, 'created_at' => $now, 'user_id' => 1, 'type' => 'comment', 'content' => '<t><p>one</p></t>', 'is_private' => 0],
            ],
            'discussion_tag' => [
                ['discussion_id' => 1, 'tag_id' => 1],
            ],
        ]);

        $this->setting('linkrobins-discussion-banners.banners', json_encode([
            ['id' => 'all', 'enabled' => true, 'placement' => 'top', 'content' => '<p>All</p>', 'scope' => 'all'],
            ['id' => 'guests', 'enabled' => true, 'placement' => 'top', 'content' => '<p>Guests</p>', 'scope' => 'all', 'groups' => [2]],
            ['id' => 'members', 'enabled' => true, 'placement' => 'top', 'content' => '<p>Members-only copy</p>', 'scope' => 'all', 'groups' => [3]],
            ['id' => 'mods', 'enabled' => true, 'placement' => 'bottom', 'content' => '<p>Mods-only copy</p>', 'scope' => 'all', 'groups' => [4]],
            ['id' => 'admins', 'enabled' => true, 'placement' => 'bottom', 'content' => '<p>Admins-only copy</p>', 'scope' => 'all', 'groups' => [['id' => 1, 'name' => 'Admins']]],
            ['id' => 'vipOrMod', 'enabled' => true, 'placement' => 'first', 'content' => '<p>VIP or mod</p>', 'scope' => 'all', 'groups' => [4, 5]],
            // Combines with the older audience setting: both must pass.
            ['id' => 'membersAndMods', 'enabled' => true, 'placement' => 'first', 'content' => '<p>x</p>', 'scope' => 'all', 'visibility' => 'guests', 'groups' => [4]],
            ['id' => 'newsMods', 'enabled' => true, 'placement' => 'stream', 'content' => '<p>News for mods</p>', 'scope' => 'only', 'tags' => [['id' => 1, 'name' => 'News']], 'groups' => [4]],
        ]));
    }

    /**
     * @return array{0: list<string>, 1: string}
     */
    private function fetch(?int $actorId): array
    {
        $options = $actorId ? ['authenticatedAs' => $actorId] : [];
        $response = $this->send($this->request('GET', '/api/discussions/1', $options));

        $this->assertEquals(200, $response->getStatusCode());

        $raw = $response->getBody()->getContents();
        $attributes = json_decode($raw, true)['data']['attributes'];

        return [array_column($attributes['linkrobinsDiscussionBanners'], 'id'), $raw];
    }

    /**
     * @return list<string>
     */
    private function ids(?int $actorId): array
    {
        return $this->fetch($actorId)[0];
    }

    #[Test]
    public function a_guest_gets_only_banners_for_everyone_or_guests(): void
    {
        $this->assertSame(['all', 'guests'], $this->ids(null));
    }

    #[Test]
    public function a_plain_member_gets_members_but_not_guests_or_staff_banners(): void
    {
        $this->assertSame(['all', 'members'], $this->ids(2));
    }

    #[Test]
    public function a_moderator_gets_moderator_banners_and_those_for_members(): void
    {
        $this->assertSame(['all', 'members', 'mods', 'vipOrMod', 'newsMods'], $this->ids(3));
    }

    #[Test]
    public function a_custom_group_is_matched_by_id(): void
    {
        $this->assertSame(['all', 'members', 'vipOrMod'], $this->ids(4));
    }

    #[Test]
    public function an_admin_is_matched_like_anyone_else(): void
    {
        $this->assertSame(['all', 'members', 'admins'], $this->ids(1));
    }

    #[Test]
    public function hidden_banners_never_reach_the_payload_at_all(): void
    {
        [, $raw] = $this->fetch(null);

        $this->assertStringNotContainsString('Members-only', $raw);
        $this->assertStringNotContainsString('Mods-only', $raw);
        $this->assertStringNotContainsString('Admins-only', $raw);
    }

    #[Test]
    public function one_viewers_result_does_not_leak_into_the_next_request(): void
    {
        $this->assertContains('mods', $this->ids(3));
        $this->assertNotContains('mods', $this->ids(null));
        $this->assertNotContains('mods', $this->ids(2));
        $this->assertContains('mods', $this->ids(3));
    }

    #[Test]
    public function the_most_specific_banner_is_chosen_among_the_visible_ones(): void
    {
        $this->setting('linkrobins-discussion-banners.most_specific', '1');
        $this->setting('linkrobins-discussion-banners.banners', json_encode([
            ['id' => 'general', 'enabled' => true, 'placement' => 'top', 'content' => '<p>General</p>', 'scope' => 'all'],
            ['id' => 'tagMods', 'enabled' => true, 'placement' => 'top', 'content' => '<p>Mods</p>', 'scope' => 'only', 'tags' => [['id' => 1, 'name' => 'News']], 'groups' => [4]],
        ]));

        // A member can't see the tag banner, so it must not suppress the general one.
        $this->assertSame(['general'], $this->ids(2));
        $this->assertSame(['general'], $this->ids(null));
        $this->assertSame(['tagMods'], $this->ids(3));
    }

    #[Test]
    public function banners_saved_before_groups_existed_stay_visible_to_everyone(): void
    {
        $this->setting('linkrobins-discussion-banners.banners', json_encode([
            ['id' => 'old', 'enabled' => true, 'placement' => 'top', 'content' => '<p>Old</p>', 'scope' => 'all', 'visibility' => 'everyone'],
        ]));

        foreach ([null, 2, 3, 1] as $actor) {
            $this->assertSame(['old'], $this->ids($actor));
        }
    }
}
