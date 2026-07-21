# Link Robins Discussion Banners

![License](https://img.shields.io/badge/license-MIT-blue.svg) [![Latest Stable Version](https://img.shields.io/packagist/v/linkrobins/discussion-banners.svg)](https://packagist.org/packages/linkrobins/discussion-banners)

A [Flarum](https://flarum.org) extension that shows configurable info banners on discussion pages. Add as many banners as you like, each in one of three placements:

- **Above the discussion**, before the first post
- **Below the discussion**, after the last post
- **Between posts**, after every Nth post

Every banner has its own on/off switch, an optional small uppercase heading (for example "Info" or "From our sponsor"), HTML content, an optional icon beside the text (an uploaded image or an emoji), an accent color, and a visibility setting: everyone, guests only, or logged-in users only.

## Where each banner appears

A banner can run across the whole forum, or only where you want it:

- **All discussions**, the default
- **Only the discussions you choose**, picked by searching for them by title (you can also paste a discussion link or ID) or by tag
- **All discussions except the ones you choose**

Because each banner carries its own text, different discussions can show completely different notices. Add one banner per notice and point each at its discussions.

Targeting and visibility are both enforced server side: a banner written for one discussion is never delivered to someone reading another, and content for one audience never reaches a different one. Banners are sent only with the discussion being viewed, so discussion lists carry no banner data at all.

Banners render as a theme-aware card that follows your forum's colors in both light and dark modes. Content HTML is sanitized before display.

## Compatibility

Works on **Flarum 1.8** and **Flarum 2.x** with the same release. No configuration differences between the two.

## Installation

```sh
composer require linkrobins/discussion-banners
```

Then enable the extension in the admin panel and configure the banners on its settings page.

## Updating

```sh
composer update linkrobins/discussion-banners
php flarum migrate
php flarum cache:clear
```

Updating from 1.0.x keeps your existing banners: the three per-placement banners are converted into the new list on `php flarum migrate`, each set to appear in all discussions.

## Links

- [Packagist](https://packagist.org/packages/linkrobins/discussion-banners)
- [Source](https://github.com/linkrobins/flarum-discussion-banners)
- [Issues](https://github.com/linkrobins/flarum-discussion-banners/issues)
