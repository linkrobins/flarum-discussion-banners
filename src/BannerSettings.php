<?php

namespace LinkRobins\DiscussionBanners;

use Flarum\Settings\SettingsRepositoryInterface;
use Illuminate\Contracts\Filesystem\Factory;

/**
 * Reads the configured banners and returns the ones a given viewer should see
 * in a given discussion.
 *
 * Banners live as a JSON list in a single setting: admins can add as many as
 * they like, each with its own placement, content and targeting. Everything
 * that reaches the frontend is normalized here, and it is normalized on READ
 * rather than on save, so a hand-edited settings row (admins can write
 * settings through the API) can never put anything unexpected in a payload.
 *
 * Shared by the Flarum 2.x DiscussionResource field and the 1.x
 * DiscussionSerializer attribute (see extend.php), so both majors gate
 * identically: a banner scoped to other discussions, or to an audience the
 * viewer isn't in, never reaches their payload at all.
 */
final class BannerSettings
{
    public const PREFIX = 'linkrobins-discussion-banners.';

    /** The JSON list of banners. */
    public const SETTING = self::PREFIX.'banners';

    public const PLACEMENTS = ['top', 'bottom', 'stream'];

    public const VISIBILITIES = ['everyone', 'guests', 'members'];

    /** all = every discussion, only = the targeted ones, except = all but those. */
    public const SCOPES = ['all', 'only', 'except'];

    public const DEFAULT_STREAM_EVERY = 8;

    /** Uploaded icons live here, and nothing outside it is ever touched. */
    public const ICON_DIR = 'linkrobins-discussion-banners/';

    /** A sane ceiling: banners are spliced into every post stream. */
    private const MAX_RULES = 50;

    private const MAX_TARGETS = 200;

    public function __construct(
        protected SettingsRepositoryInterface $settings,
        protected Factory $filesystem,
    ) {
    }

    /**
     * Every configured banner, normalized, including its targeting.
     *
     * @return list<array<string, mixed>>
     */
    public function rules(): array
    {
        $raw = (string) $this->settings->get(self::SETTING);

        if (trim($raw) !== '') {
            $decoded = json_decode($raw, true);

            if (is_array($decoded)) {
                return self::normalize($decoded);
            }
        }

        // Nothing saved in the new format yet: fall back to the three
        // single-placement banners of 1.0.x, so an install that has updated
        // the code but not yet run migrations keeps showing its banners.
        return self::fromLegacySettings($this->legacyValues());
    }

    /**
     * The banners to render for a viewer of a given discussion.
     *
     * @param  \Closure(): list<int>|null  $tagIds  Resolves the discussion's tag ids. Only
     *                                              called if some banner targets tags, so
     *                                              installs without tag targeting pay nothing.
     * @return list<array<string, mixed>>
     */
    public function visibleIn(bool $isGuest, ?int $discussionId = null, ?\Closure $tagIds = null): array
    {
        $banners = [];
        $tags = null;

        foreach ($this->rules() as $rule) {
            if (! $rule['enabled'] || $rule['content'] === '') {
                continue;
            }

            if (($rule['visibility'] === 'guests' && ! $isGuest) || ($rule['visibility'] === 'members' && $isGuest)) {
                continue;
            }

            if ($rule['scope'] !== 'all') {
                if ($rule['tags'] !== [] && $tags === null) {
                    $tags = $tagIds ? array_map('intval', $tagIds()) : [];
                }

                $targeted = ($discussionId !== null && in_array($discussionId, $rule['discussions'], true))
                    || ($rule['tags'] !== [] && array_intersect($rule['tags'], $tags ?? []) !== []);

                // "Only these" with nothing selected targets nothing, which is
                // what it says; the admin page warns about that case.
                if (($rule['scope'] === 'only') !== $targeted) {
                    continue;
                }
            }

            $banner = [
                'id' => $rule['id'],
                'placement' => $rule['placement'],
                'label' => $rule['label'],
                'contentHtml' => $rule['content'],
            ];

            if ($rule['color'] !== '') {
                $banner['color'] = $rule['color'];
            }

            if ($icon = $this->icon($rule)) {
                $banner['icon'] = $icon;
            }

            if ($rule['placement'] === 'stream') {
                $banner['every'] = $rule['every'];
            }

            $banners[] = $banner;
        }

        return $banners;
    }

    /**
     * Every icon file the given banners refer to, for pruning uploads that no
     * banner uses any more.
     *
     * @param  list<array<string, mixed>>|null  $rules  Defaults to the saved ones.
     * @return list<string>
     */
    public function iconPaths(?array $rules = null): array
    {
        $paths = [];

        foreach ($rules ?? $this->rules() as $rule) {
            $path = $rule['icon']['path'] ?? '';

            if (is_string($path) && $path !== '') {
                $paths[] = $path;
            }
        }

        return array_values(array_unique($paths));
    }

    /**
     * @param  array<mixed>  $decoded
     * @return list<array<string, mixed>>
     */
    public static function normalize(array $decoded): array
    {
        $rules = [];
        $seen = [];

        foreach (array_slice($decoded, 0, self::MAX_RULES) as $index => $raw) {
            if (! is_array($raw)) {
                continue;
            }

            $id = preg_replace('/[^A-Za-z0-9_-]/', '', (string) ($raw['id'] ?? ''));
            $id = is_string($id) ? substr($id, 0, 32) : '';

            // Ids key the rendered banners and name uploaded icon files, so
            // they have to exist and be unique even if the JSON was edited by
            // hand.
            if ($id === '' || isset($seen[$id])) {
                $id = 'b'.$index;
            }
            $seen[$id] = true;

            $placement = (string) ($raw['placement'] ?? '');
            $visibility = (string) ($raw['visibility'] ?? '');
            $scope = (string) ($raw['scope'] ?? '');
            $every = (int) ($raw['every'] ?? 0);

            $color = trim((string) ($raw['color'] ?? ''));
            // Strict hex only: this lands in an inline CSS custom property.
            if (! preg_match('/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $color)) {
                $color = '';
            }

            $rules[] = [
                'id' => $id,
                'enabled' => (bool) ($raw['enabled'] ?? false),
                'placement' => in_array($placement, self::PLACEMENTS, true) ? $placement : 'top',
                // Below 2 the stream would be mostly banners.
                'every' => $every >= 2 ? $every : self::DEFAULT_STREAM_EVERY,
                'label' => mb_substr(trim((string) ($raw['label'] ?? '')), 0, 200),
                'content' => trim((string) ($raw['content'] ?? '')),
                'visibility' => in_array($visibility, self::VISIBILITIES, true) ? $visibility : 'everyone',
                'color' => strtolower($color),
                'icon' => self::normalizeIcon($raw['icon'] ?? null),
                'scope' => in_array($scope, self::SCOPES, true) ? $scope : 'all',
                'discussions' => self::ids($raw['discussions'] ?? []),
                'tags' => self::ids($raw['tags'] ?? []),
            ];
        }

        return $rules;
    }

    /**
     * Build the new-format rules from the three per-placement banners of
     * 1.0.x. Used both by the update migration and as a read-time fallback.
     *
     * @param  array<string, mixed>  $values  Keyed by full setting key.
     * @return list<array<string, mixed>>
     */
    public static function fromLegacySettings(array $values): array
    {
        $rules = [];

        foreach (self::PLACEMENTS as $placement) {
            $prefix = self::PREFIX.$placement;
            $get = fn (string $key) => (string) ($values[$prefix.$key] ?? '');

            if (! (bool) $get('_enabled') && trim($get('_content')) === '') {
                continue;
            }

            $rules[] = [
                'id' => $placement,
                'enabled' => (bool) $get('_enabled'),
                'placement' => $placement,
                'every' => (int) ($values[self::PREFIX.'stream_every'] ?? 0),
                'label' => $get('_label'),
                'content' => $get('_content'),
                'visibility' => $get('_visibility'),
                'color' => $get('_color'),
                'icon' => [
                    'type' => $get('_icon_type'),
                    'path' => $get('_icon_path'),
                    'url' => $get('_icon_url'),
                    'emoji' => $get('_icon_emoji'),
                ],
                'scope' => 'all',
                'discussions' => [],
                'tags' => [],
            ];
        }

        return self::normalize($rules);
    }

    /**
     * @param  mixed  $raw
     * @return array{type: string, path: string, url: string, emoji: string}
     */
    private static function normalizeIcon($raw): array
    {
        $icon = is_array($raw) ? $raw : [];
        $type = (string) ($icon['type'] ?? '');

        $path = (string) ($icon['path'] ?? '');
        // Only ever our own upload directory: the path is read back to build a
        // URL and to decide which files to prune.
        if (! str_starts_with($path, self::ICON_DIR) || str_contains($path, '..')) {
            $path = '';
        }

        return [
            'type' => in_array($type, ['image', 'emoji'], true) ? $type : '',
            'path' => $path,
            'url' => (string) ($icon['url'] ?? ''),
            // Enough room for ZWJ sequences and flags, but nothing essay-sized.
            'emoji' => mb_substr(trim((string) ($icon['emoji'] ?? '')), 0, 16),
        ];
    }

    /**
     * @param  mixed  $raw  A list of ids, or of {id: ...} objects as the admin page stores them.
     * @return list<int>
     */
    private static function ids($raw): array
    {
        if (! is_array($raw)) {
            return [];
        }

        $ids = [];

        foreach (array_slice($raw, 0, self::MAX_TARGETS) as $entry) {
            $id = is_array($entry) ? ($entry['id'] ?? null) : $entry;

            if (is_numeric($id) && (int) $id > 0) {
                $ids[] = (int) $id;
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * @param  array<string, mixed>  $rule
     * @return array{type: string, url?: string, emoji?: string}|null
     */
    private function icon(array $rule): ?array
    {
        $icon = $rule['icon'];

        if ($icon['type'] === 'emoji' && $icon['emoji'] !== '') {
            return ['type' => 'emoji', 'emoji' => $icon['emoji']];
        }

        if ($icon['type'] === 'image' && $icon['path'] !== '') {
            // The URL is resolved from the stored path at read time so it
            // survives base-URL changes and CDN'd asset disks.
            try {
                return ['type' => 'image', 'url' => $this->filesystem->disk('flarum-assets')->url($icon['path'])];
            } catch (\Throwable $e) {
                return null;
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function legacyValues(): array
    {
        $values = [self::PREFIX.'stream_every' => $this->settings->get(self::PREFIX.'stream_every')];

        foreach (self::PLACEMENTS as $placement) {
            foreach (['_enabled', '_label', '_content', '_visibility', '_color', '_icon_type', '_icon_path', '_icon_url', '_icon_emoji'] as $key) {
                $values[self::PREFIX.$placement.$key] = $this->settings->get(self::PREFIX.$placement.$key);
            }
        }

        return $values;
    }
}
