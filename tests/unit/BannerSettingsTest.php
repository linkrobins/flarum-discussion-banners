<?php

/*
 * This file is part of linkrobins/discussion-banners.
 *
 * For detailed copyright and license information, please view the
 * LICENSE file that was distributed with this source code.
 */

namespace LinkRobins\DiscussionBanners\Tests\unit;

use Flarum\Settings\SettingsRepositoryInterface;
use Illuminate\Contracts\Filesystem\Factory;
use LinkRobins\DiscussionBanners\BannerSettings;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class BannerSettingsTest extends TestCase
{
    /**
     * @param array<string, string> $values Keyed by setting key without the extension prefix.
     */
    private function settings(array $values): BannerSettings
    {
        $settings = $this->createStub(SettingsRepositoryInterface::class);
        $settings->method('get')->willReturnCallback(
            fn ($key) => $values[str_replace(BannerSettings::PREFIX, '', $key)] ?? ''
        );

        return new BannerSettings($settings, $this->createStub(Factory::class));
    }

    /**
     * @param array<int, array<string, mixed>> $rules
     * @param array<string, string> $extra
     */
    private function banners(array $rules, bool $isGuest = true, ?int $discussionId = null, ?\Closure $tagIds = null, array $extra = []): array
    {
        return $this->settings(['banners' => json_encode($rules)] + $extra)
            ->visibleIn($isGuest, $discussionId, $tagIds);
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function rule(array $overrides = []): array
    {
        return $overrides + [
            'id' => 'a',
            'enabled' => true,
            'placement' => 'top',
            'content' => '<p>Hi</p>',
            'scope' => 'all',
        ];
    }

    #[Test]
    public function nothing_is_returned_by_default(): void
    {
        $this->assertSame([], $this->settings([])->visibleIn(true, 1));
    }

    #[Test]
    public function an_enabled_banner_with_content_is_returned(): void
    {
        $result = $this->banners([$this->rule()]);

        $this->assertCount(1, $result);
        $this->assertSame('top', $result[0]['placement']);
        $this->assertSame('<p>Hi</p>', $result[0]['contentHtml']);
        $this->assertSame('a', $result[0]['id']);
    }

    #[Test]
    public function disabled_or_empty_banners_are_skipped(): void
    {
        $this->assertSame([], $this->banners([$this->rule(['enabled' => false])]));
        $this->assertSame([], $this->banners([$this->rule(['content' => '   '])]));
    }

    #[Test]
    public function several_banners_can_share_a_placement(): void
    {
        $result = $this->banners([
            $this->rule(['id' => 'a', 'content' => 'first']),
            $this->rule(['id' => 'b', 'content' => 'second']),
        ]);

        $this->assertSame(['first', 'second'], array_column($result, 'contentHtml'));
    }

    #[Test]
    public function members_only_banners_are_hidden_from_guests_and_vice_versa(): void
    {
        $rules = [
            $this->rule(['id' => 'm', 'content' => 'members', 'visibility' => 'members']),
            $this->rule(['id' => 'g', 'content' => 'guests', 'visibility' => 'guests']),
        ];

        $this->assertSame(['guests'], array_column($this->banners($rules, true), 'contentHtml'));
        $this->assertSame(['members'], array_column($this->banners($rules, false), 'contentHtml'));
    }

    #[Test]
    public function a_banner_scoped_to_chosen_discussions_appears_only_there(): void
    {
        $rules = [$this->rule(['scope' => 'only', 'discussions' => [44, 51]])];

        $this->assertCount(1, $this->banners($rules, true, 44));
        $this->assertCount(1, $this->banners($rules, true, 51));
        $this->assertSame([], $this->banners($rules, true, 9));
        $this->assertSame([], $this->banners($rules, true, null));
    }

    #[Test]
    public function the_admin_page_stores_targets_as_objects_with_titles(): void
    {
        // What the discussion picker actually saves: {id, title} entries.
        $rules = [$this->rule(['scope' => 'only', 'discussions' => [['id' => 44, 'title' => 'Welcome']]])];

        $this->assertCount(1, $this->banners($rules, true, 44));
        $this->assertSame([], $this->banners($rules, true, 45));
    }

    #[Test]
    public function an_excluded_discussion_gets_everything_else(): void
    {
        $rules = [$this->rule(['scope' => 'except', 'discussions' => [44]])];

        $this->assertSame([], $this->banners($rules, true, 44));
        $this->assertCount(1, $this->banners($rules, true, 9));
    }

    #[Test]
    public function tag_targeting_matches_the_discussions_tags(): void
    {
        $rules = [$this->rule(['scope' => 'only', 'tags' => [['id' => 3, 'name' => 'Announcements']]])];
        $tags = fn () => [3, 7];

        $this->assertCount(1, $this->banners($rules, true, 9, $tags));
        $this->assertSame([], $this->banners($rules, true, 9, fn () => [7]));
    }

    #[Test]
    public function tags_are_only_resolved_when_a_banner_targets_them(): void
    {
        $resolved = 0;
        $count = function () use (&$resolved) {
            $resolved++;

            return [1];
        };

        $this->banners([$this->rule()], true, 9, $count);
        $this->assertSame(0, $resolved, 'An untargeted banner must not cost a tag lookup.');

        $this->banners([
            $this->rule(['id' => 'a', 'scope' => 'only', 'tags' => [1]]),
            $this->rule(['id' => 'b', 'scope' => 'only', 'tags' => [2]]),
        ], true, 9, $count);
        $this->assertSame(1, $resolved, 'Tags must be resolved once per request, not once per banner.');
    }

    #[Test]
    public function targeting_nothing_shows_nothing(): void
    {
        $this->assertSame([], $this->banners([$this->rule(['scope' => 'only'])], true, 44));
        $this->assertCount(1, $this->banners([$this->rule(['scope' => 'except'])], true, 44));
    }

    #[Test]
    public function only_strict_hex_colors_are_serialized(): void
    {
        $this->assertSame('#a1b2c3', $this->banners([$this->rule(['color' => '#A1B2C3'])])[0]['color']);
        $this->assertSame('#abc', $this->banners([$this->rule(['color' => '#abc'])])[0]['color']);
        $this->assertArrayNotHasKey('color', $this->banners([$this->rule(['color' => 'red'])])[0]);
        $this->assertArrayNotHasKey('color', $this->banners([$this->rule(['color' => '#abc; color: red'])])[0]);
    }

    #[Test]
    public function emoji_icons_are_trimmed_and_length_capped(): void
    {
        $icon = fn (string $emoji) => $this->banners([$this->rule(['icon' => ['type' => 'emoji', 'emoji' => $emoji]])])[0]['icon'] ?? null;

        $this->assertSame(['type' => 'emoji', 'emoji' => '🎉'], $icon(' 🎉 '));
        $this->assertSame(16, mb_strlen($icon(str_repeat('a', 40))['emoji']));
        $this->assertNull($icon(' '));
    }

    #[Test]
    public function image_icons_outside_our_own_directory_are_dropped(): void
    {
        // Settings are writable by any admin through the API: a hand-edited
        // path must not be able to point at another extension's files.
        $rules = [$this->rule(['icon' => ['type' => 'image', 'path' => 'assets/logo.png']])];

        $this->assertSame('', BannerSettings::normalize($rules)[0]['icon']['path']);
        $this->assertArrayNotHasKey('icon', $this->banners($rules)[0]);
    }

    #[Test]
    public function the_stream_cadence_is_clamped_to_a_sane_minimum(): void
    {
        $every = fn ($value) => $this->banners([$this->rule(['placement' => 'stream', 'every' => $value])])[0]['every'];

        $this->assertSame(2, $every(2));
        $this->assertSame(BannerSettings::DEFAULT_STREAM_EVERY, $every(1));
        $this->assertSame(BannerSettings::DEFAULT_STREAM_EVERY, $every(0));
        $this->assertSame(BannerSettings::DEFAULT_STREAM_EVERY, $every(-3));
    }

    #[Test]
    public function only_the_stream_placement_carries_a_cadence(): void
    {
        $this->assertArrayNotHasKey('every', $this->banners([$this->rule()])[0]);
    }

    #[Test]
    public function unparseable_or_hand_broken_values_never_throw(): void
    {
        $this->assertSame([], $this->settings(['banners' => 'not json'])->visibleIn(true, 1));
        $this->assertSame([], $this->settings(['banners' => '"a string"'])->visibleIn(true, 1));
        $this->assertSame([], $this->settings(['banners' => '[1, 2, null]'])->visibleIn(true, 1));
    }

    #[Test]
    public function banners_configured_on_1_0_are_still_read_before_the_migration_runs(): void
    {
        $legacy = [
            'top_enabled' => '1',
            'top_content' => '<p>Old</p>',
            'top_label' => 'Info',
            'bottom_enabled' => '1',
            'bottom_content' => '<p>Members</p>',
            'bottom_visibility' => 'members',
        ];

        $guest = $this->settings($legacy)->visibleIn(true, 1);
        $this->assertSame(['<p>Old</p>'], array_column($guest, 'contentHtml'));
        $this->assertSame('Info', $guest[0]['label']);

        $member = $this->settings($legacy)->visibleIn(false, 1);
        $this->assertSame(['<p>Old</p>', '<p>Members</p>'], array_column($member, 'contentHtml'));

        // ... and once converted, the list wins over the old keys.
        $converted = $this->settings($legacy + ['banners' => '[]'])->visibleIn(false, 1);
        $this->assertSame([], $converted);
    }

    #[Test]
    public function the_1_0_conversion_keeps_every_placement_and_targets_everything(): void
    {
        $rules = BannerSettings::fromLegacySettings([
            BannerSettings::PREFIX.'top_enabled' => '1',
            BannerSettings::PREFIX.'top_content' => 'top',
            BannerSettings::PREFIX.'stream_enabled' => '1',
            BannerSettings::PREFIX.'stream_content' => 'stream',
            BannerSettings::PREFIX.'stream_every' => '5',
            BannerSettings::PREFIX.'stream_icon_type' => 'image',
            BannerSettings::PREFIX.'stream_icon_path' => BannerSettings::ICON_DIR.'stream-abc.png',
        ]);

        $this->assertSame(['top', 'stream'], array_column($rules, 'placement'));
        $this->assertSame(['all', 'all'], array_column($rules, 'scope'));
        $this->assertSame(5, $rules[1]['every']);
        $this->assertSame(BannerSettings::ICON_DIR.'stream-abc.png', $rules[1]['icon']['path']);
    }

    #[Test]
    public function icon_paths_lists_what_is_still_in_use(): void
    {
        $settings = $this->settings(['banners' => json_encode([
            $this->rule(['id' => 'a', 'icon' => ['type' => 'image', 'path' => BannerSettings::ICON_DIR.'a.png']]),
            $this->rule(['id' => 'b', 'icon' => ['type' => 'emoji', 'emoji' => '🎉']]),
        ])]);

        $this->assertSame([BannerSettings::ICON_DIR.'a.png'], $settings->iconPaths());
    }
}
