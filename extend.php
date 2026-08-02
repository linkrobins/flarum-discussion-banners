<?php

use Flarum\Extend;
use LinkRobins\DiscussionBanners\AddDiscussionBannersV2;
use LinkRobins\DiscussionBanners\BannerSettings;
use LinkRobins\DiscussionBanners\PruneIcons;
use LinkRobins\DiscussionBanners\UploadIconController;

$extenders = [
    (new Extend\Frontend('forum'))
        ->js(__DIR__.'/js/dist/forum.js')
        ->css(__DIR__.'/less/forum.less'),

    (new Extend\Frontend('admin'))
        ->js(__DIR__.'/js/dist/admin.js')
        ->css(__DIR__.'/less/admin.less'),

    new Extend\Locales(__DIR__.'/locale'),

    (new Extend\Routes('api'))
        ->post('/linkrobins-discussion-banners/{banner}/icon', 'linkrobins-discussion-banners.icon.upload', UploadIconController::class),

    (new Extend\Event())
        ->listen(\Flarum\Settings\Event\Saved::class, PruneIcons::class),

    (new Extend\Settings())
        // Every banner lives in this one JSON list, so admins can add as many
        // as they like without a settings key per banner.
        ->default(BannerSettings::SETTING, '')

        // The three single-placement banners of 1.0.x. Still declared so an
        // install that rolls back to 1.0.x keeps its banners: the migration
        // copies these into the list above, and BannerSettings falls back to
        // reading them when the list is empty.
        ->default(BannerSettings::PREFIX.'top_enabled', '0')
        ->default(BannerSettings::PREFIX.'top_label', '')
        ->default(BannerSettings::PREFIX.'top_content', '')
        ->default(BannerSettings::PREFIX.'top_visibility', 'everyone')
        ->default(BannerSettings::PREFIX.'top_color', '')
        ->default(BannerSettings::PREFIX.'top_icon_type', '')
        ->default(BannerSettings::PREFIX.'top_icon_path', '')
        ->default(BannerSettings::PREFIX.'top_icon_url', '')
        ->default(BannerSettings::PREFIX.'top_icon_emoji', '')
        ->default(BannerSettings::PREFIX.'bottom_enabled', '0')
        ->default(BannerSettings::PREFIX.'bottom_label', '')
        ->default(BannerSettings::PREFIX.'bottom_content', '')
        ->default(BannerSettings::PREFIX.'bottom_visibility', 'everyone')
        ->default(BannerSettings::PREFIX.'bottom_color', '')
        ->default(BannerSettings::PREFIX.'bottom_icon_type', '')
        ->default(BannerSettings::PREFIX.'bottom_icon_path', '')
        ->default(BannerSettings::PREFIX.'bottom_icon_url', '')
        ->default(BannerSettings::PREFIX.'bottom_icon_emoji', '')
        ->default(BannerSettings::PREFIX.'stream_enabled', '0')
        ->default(BannerSettings::PREFIX.'stream_label', '')
        ->default(BannerSettings::PREFIX.'stream_content', '')
        ->default(BannerSettings::PREFIX.'stream_visibility', 'everyone')
        ->default(BannerSettings::PREFIX.'stream_color', '')
        ->default(BannerSettings::PREFIX.'stream_icon_type', '')
        ->default(BannerSettings::PREFIX.'stream_icon_path', '')
        ->default(BannerSettings::PREFIX.'stream_icon_url', '')
        ->default(BannerSettings::PREFIX.'stream_icon_emoji', '')
        ->default(BannerSettings::PREFIX.'stream_every', (string) BannerSettings::DEFAULT_STREAM_EVERY),
];

// The banners for a discussion are serialized onto that discussion. Audience
// and discussion targeting are enforced inside BannerSettings, server-side, so
// the payload only ever carries banners the actor is allowed to see.
$extenders[] = (new Extend\ApiResource(\Flarum\Api\Resource\DiscussionResource::class))
    ->fields(AddDiscussionBannersV2::class);

return $extenders;
