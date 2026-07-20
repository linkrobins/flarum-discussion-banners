<?php

use Flarum\Extend;
use LinkRobins\DiscussionBanners\AddForumBannersV1;
use LinkRobins\DiscussionBanners\AddForumBannersV2;
use LinkRobins\DiscussionBanners\BannerSettings;
use LinkRobins\DiscussionBanners\DeleteIconController;
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
        ->post('/linkrobins-discussion-banners/{placement}/icon', 'linkrobins-discussion-banners.icon.upload', UploadIconController::class)
        ->delete('/linkrobins-discussion-banners/{placement}/icon', 'linkrobins-discussion-banners.icon.delete', DeleteIconController::class),

    (new Extend\Settings())
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

// The forum attribute carrying the viewer's banners is registered through
// whichever serialization API this Flarum major provides. Both paths share
// BannerSettings, so visibility gating is identical.
if (class_exists(Extend\ApiResource::class)) {
    // Flarum 2.x
    $extenders[] = (new Extend\ApiResource(\Flarum\Api\Resource\ForumResource::class))
        ->fields(AddForumBannersV2::class);
} elseif (class_exists(Extend\ApiSerializer::class)) {
    // Flarum 1.x
    $extenders[] = (new Extend\ApiSerializer(\Flarum\Api\Serializer\ForumSerializer::class))
        ->attributes(AddForumBannersV1::class);
}

return $extenders;
