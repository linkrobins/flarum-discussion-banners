# Discussion Banners

![License](https://img.shields.io/badge/license-MIT-blue.svg) [![Latest Stable Version](https://img.shields.io/packagist/v/linkrobins/discussion-banners.svg)](https://packagist.org/packages/linkrobins/discussion-banners)

A [Flarum](https://flarum.org) extension that shows configurable info banners on discussion pages, in up to three placements:

- **Above the discussion**, before the first post
- **Below the discussion**, after the last post
- **Between posts**, after every Nth post

Each placement has its own on/off switch, an optional small uppercase heading (for example "Info" or "From our sponsor"), HTML content, an optional icon beside the text (an uploaded image or an emoji), and a visibility setting: everyone, guests only, or logged-in users only. Visibility is enforced server side, so content for one audience is never delivered to another.

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
php flarum cache:clear
```

## Links

- [Packagist](https://packagist.org/packages/linkrobins/discussion-banners)
- [Source](https://github.com/linkrobins/flarum-discussion-banners)
- [Issues](https://github.com/linkrobins/flarum-discussion-banners/issues)
