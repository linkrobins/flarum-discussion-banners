<?php

/*
 * This file is part of linkrobins/discussion-banners.
 *
 * For detailed copyright and license information, please view the
 * LICENSE file that was distributed with this source code.
 */

namespace LinkRobins\DiscussionBanners\Tests\integration\api;

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
        ]);

        // Settings must go through the setting() helper (not prepareDatabase):
        // the settings repository is memory-cached at boot, before database
        // seeding runs.
        $this->setting('linkrobins-discussion-banners.top_enabled', '1');
        $this->setting('linkrobins-discussion-banners.top_content', '<p>Hello</p>');
        $this->setting('linkrobins-discussion-banners.top_visibility', 'everyone');
        $this->setting('linkrobins-discussion-banners.bottom_enabled', '1');
        $this->setting('linkrobins-discussion-banners.bottom_content', '<p>Members zone</p>');
        $this->setting('linkrobins-discussion-banners.bottom_visibility', 'members');
    }

    /**
     * @return list<string>
     */
    private function placements(?int $actorId): array
    {
        $options = $actorId ? ['authenticatedAs' => $actorId] : [];
        $response = $this->send($this->request('GET', '/api', $options));

        $this->assertEquals(200, $response->getStatusCode());

        $attributes = json_decode($response->getBody()->getContents(), true)['data']['attributes'];
        $this->assertArrayHasKey('linkrobinsDiscussionBanners', $attributes);

        $placements = array_map(fn (array $banner) => $banner['placement'], $attributes['linkrobinsDiscussionBanners']);
        sort($placements);

        return $placements;
    }

    #[Test]
    public function guests_get_everyone_banners_but_not_members_only_ones(): void
    {
        $this->assertSame(['top'], $this->placements(null));
    }

    #[Test]
    public function members_get_members_only_banners_too(): void
    {
        $this->assertSame(['bottom', 'top'], $this->placements(2));
    }
}
