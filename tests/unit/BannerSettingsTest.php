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
     * @param array<string, string> $values
     */
    private function banners(array $values, bool $isGuest): array
    {
        $settings = $this->createStub(SettingsRepositoryInterface::class);
        $settings->method('get')->willReturnCallback(
            fn ($key) => $values[str_replace(BannerSettings::PREFIX, '', $key)] ?? ''
        );

        $filesystem = $this->createStub(Factory::class);

        return (new BannerSettings($settings, $filesystem))->visibleTo($isGuest);
    }

    #[Test]
    public function nothing_is_returned_by_default(): void
    {
        $this->assertSame([], $this->banners([], true));
    }

    #[Test]
    public function an_enabled_banner_with_content_is_returned(): void
    {
        $result = $this->banners(['top_enabled' => '1', 'top_content' => '<p>Hi</p>'], true);

        $this->assertCount(1, $result);
        $this->assertSame('top', $result[0]['placement']);
        $this->assertSame('<p>Hi</p>', $result[0]['contentHtml']);
    }

    #[Test]
    public function an_enabled_banner_without_content_is_skipped(): void
    {
        $this->assertSame([], $this->banners(['top_enabled' => '1', 'top_content' => '   '], true));
    }

    #[Test]
    public function members_only_banners_are_hidden_from_guests_and_vice_versa(): void
    {
        $values = [
            'top_enabled' => '1', 'top_content' => 'members', 'top_visibility' => 'members',
            'bottom_enabled' => '1', 'bottom_content' => 'guests', 'bottom_visibility' => 'guests',
        ];

        $guest = $this->banners($values, true);
        $this->assertCount(1, $guest);
        $this->assertSame('bottom', $guest[0]['placement']);

        $member = $this->banners($values, false);
        $this->assertCount(1, $member);
        $this->assertSame('top', $member[0]['placement']);
    }

    #[Test]
    public function only_strict_hex_colors_are_serialized(): void
    {
        $base = ['top_enabled' => '1', 'top_content' => 'x'];

        $this->assertSame('#a1b2c3', $this->banners($base + ['top_color' => '#A1B2C3'], true)[0]['color']);
        $this->assertSame('#abc', $this->banners($base + ['top_color' => '#abc'], true)[0]['color']);
        $this->assertArrayNotHasKey('color', $this->banners($base + ['top_color' => 'red'], true)[0]);
        $this->assertArrayNotHasKey('color', $this->banners($base + ['top_color' => '#abc; color: red'], true)[0]);
    }

    #[Test]
    public function emoji_icons_are_trimmed_and_length_capped(): void
    {
        $base = ['top_enabled' => '1', 'top_content' => 'x', 'top_icon_type' => 'emoji'];

        $icon = $this->banners($base + ['top_icon_emoji' => ' 🎉 '], true)[0]['icon'];
        $this->assertSame(['type' => 'emoji', 'emoji' => '🎉'], $icon);

        $long = str_repeat('a', 40);
        $icon = $this->banners($base + ['top_icon_emoji' => $long], true)[0]['icon'];
        $this->assertSame(16, mb_strlen($icon['emoji']));

        $this->assertArrayNotHasKey('icon', $this->banners($base + ['top_icon_emoji' => ' '], true)[0]);
    }

    #[Test]
    public function the_stream_cadence_is_clamped_to_a_sane_minimum(): void
    {
        $base = ['stream_enabled' => '1', 'stream_content' => 'x'];

        $this->assertSame(2, $this->banners($base + ['stream_every' => '2'], true)[0]['every']);
        $this->assertSame(BannerSettings::DEFAULT_STREAM_EVERY, $this->banners($base + ['stream_every' => '1'], true)[0]['every']);
        $this->assertSame(BannerSettings::DEFAULT_STREAM_EVERY, $this->banners($base + ['stream_every' => '0'], true)[0]['every']);
        $this->assertSame(BannerSettings::DEFAULT_STREAM_EVERY, $this->banners($base + ['stream_every' => '-3'], true)[0]['every']);
    }
}
