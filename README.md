# Link Robins Discussion Banners

![License](https://img.shields.io/badge/license-MIT-blue.svg) [![Latest Stable Version](https://img.shields.io/packagist/v/linkrobins/discussion-banners.svg)](https://packagist.org/packages/linkrobins/discussion-banners)

A [Flarum](https://flarum.org) extension that shows configurable info banners on discussion pages. Add as many banners as you like, each in one of four placements:

- **Above the discussion**, before the first post
- **After the first post**
- **Below the discussion**, after the last post
- **Between posts**, after every Nth post (every 8th by default, 2 at minimum)

Every banner has its own on/off switch, an optional small uppercase heading (for example "Info" or "From our sponsor"), HTML content, an optional icon beside the text (an uploaded PNG, JPEG, GIF or WebP image up to 2 MB, or an emoji), an accent color, a visibility setting (everyone, guests only, or logged-in users only), and an optional list of user groups. You can keep up to 50 banners.

The accent color tints the banner's border, background and small heading. Leave it blank to use your theme color. Body text always follows the theme, so banners stay readable in light and dark modes.

## Who sees each banner

Each banner has a **Visible to groups** picker listing every group on your forum, including Admins, Moderators, your own groups, **Guests** (visitors who are not logged in) and **Members** (everyone logged in). A banner shows to a viewer who is in at least one of the groups you pick. Pick none and it shows to everyone, so banners you already have are unchanged.

- The picker narrows the **Who sees it** setting: when both are set, the viewer has to pass both.
- Admins are matched like anyone else. To see a banner meant for moderators, an admin needs to be in a group it lists. In the admin panel you always see and edit every banner.
- If you delete a group, a banner that listed only that group has nobody left to show to. Pick new groups or clear the list.

When **Show only the most specific banner** is on, the most specific one is chosen among the banners the viewer can see, so a banner hidden from a viewer never replaces a general one they should get.

## Where each banner appears

A banner can run across the whole forum, or only where you want it:

- **All discussions**, the default
- **Only the discussions you choose**, picked by searching for them by title (you can also paste a discussion link or ID) or by tag
- **All discussions except the ones you choose**

Because each banner carries its own text, different discussions can show completely different notices. Add one banner per notice and point each at its discussions.

By default, every banner that matches a discussion is shown, stacked in the order you listed them. Turn on **Show only the most specific banner** to have the closest match win instead: in each placement, a banner picked for that discussion beats one for its subtag, which beats one for a tag, which beats one shown in every discussion. So a general notice can be replaced in one tag, or in a single discussion, without turning it off elsewhere.

Targeting, audience and groups are all enforced server side: a banner written for one discussion is never delivered to someone reading another, and content for one audience or group never reaches a different one. Banners are sent only with the discussion being viewed, so discussion lists carry no banner data at all.

Targeting by tag needs [Flarum Tags](https://github.com/flarum/framework/tree/2.x/extensions/tags) enabled; without it, banners still work and can be aimed at individual discussions. A banner left on "Only the discussions I choose" with nothing selected appears nowhere, and "All discussions except" with nothing selected appears everywhere. Icons you upload but never save, or that you replace or remove, are cleaned up automatically.

Banners render as a theme-aware card that follows your forum's colors in both light and dark modes. Content HTML is sanitized before display.

## Compatibility

This is the 2.x line, for **Flarum 2.0**. Flarum 1.8 is served by the 1.x line, which no longer gets updates.

Banners also appear on [FoF Blog](https://github.com/FriendsOfFlarum/blog) articles: above the article, right after it, and among its comments.

## Installation

```sh
composer require linkrobins/discussion-banners
```

Then switch it on in the admin panel's Extensions page and add your banners on its settings page. No permissions are involved: who sees each banner is set per banner.

## Updating

```sh
composer update linkrobins/discussion-banners
php flarum migrate
php flarum cache:clear
```

The group list is stored with each banner, so this update needs no migration. Updating from 1.0.x keeps your existing banners: the three per-placement banners are converted into the new list on `php flarum migrate`, each set to appear in all discussions.

## Links

- [Packagist](https://packagist.org/packages/linkrobins/discussion-banners)
- [Source](https://github.com/linkrobins/flarum-discussion-banners)
- [Issues](https://github.com/linkrobins/flarum-discussion-banners/issues)
