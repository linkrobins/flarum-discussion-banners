<?php

namespace LinkRobins\DiscussionBanners;

use Flarum\Foundation\ValidationException;
use Flarum\Http\RequestUtil;
use Flarum\Settings\SettingsRepositoryInterface;
use Illuminate\Contracts\Filesystem\Factory;
use Illuminate\Support\Arr;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UploadedFileInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * POST /api/linkrobins-discussion-banners/{placement}/icon
 *
 * Admin-only multipart upload of a banner icon image. Stored on the
 * flarum-assets disk (multi-server / CDN safe); the settings keep the disk
 * PATH and the public URL is resolved at read time. Uploading also switches
 * the placement's icon type to "image" so the icon shows without a separate
 * save. Raster formats only: an <img> never executes SVG scripts, but there
 * is no reason to store markup as an icon either.
 */
final class UploadIconController implements RequestHandlerInterface
{
    private const MAX_BYTES = 2 * 1024 * 1024;

    private const MIME_EXT = [
        'image/png' => 'png',
        'image/jpeg' => 'jpg',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
    ];

    public function __construct(
        protected SettingsRepositoryInterface $settings,
        protected Factory $filesystem,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        RequestUtil::getActor($request)->assertAdmin();

        $placement = (string) Arr::get($request->getQueryParams(), 'placement');
        if (! in_array($placement, BannerSettings::PLACEMENTS, true)) {
            throw new ValidationException(['icon' => 'Unknown banner placement.']);
        }

        $file = Arr::get($request->getUploadedFiles(), 'icon');
        if (! $file instanceof UploadedFileInterface || $file->getError() !== UPLOAD_ERR_OK) {
            throw new ValidationException(['icon' => 'No icon file was uploaded.']);
        }
        // Size-check BEFORE reading the stream into memory. A null size never
        // happens for real SAPI uploads, so treat it as invalid rather than
        // reading an unbounded stream to find out.
        if ($file->getSize() === null || $file->getSize() > self::MAX_BYTES) {
            throw new ValidationException(['icon' => 'The icon must be smaller than 2 MB.']);
        }

        $contents = (string) $file->getStream();
        if ($contents === '' || strlen($contents) > self::MAX_BYTES) {
            throw new ValidationException(['icon' => 'The icon must be smaller than 2 MB.']);
        }

        // Trust the bytes, not the client's declared type or filename.
        $mime = (string) (new \finfo(FILEINFO_MIME_TYPE))->buffer($contents);
        $ext = self::MIME_EXT[$mime] ?? null;
        if ($ext === null) {
            throw new ValidationException(['icon' => 'The icon must be a PNG, JPEG, GIF or WebP image.']);
        }

        $prefix = BannerSettings::PREFIX.$placement;
        $disk = $this->filesystem->disk('flarum-assets');

        $path = 'linkrobins-discussion-banners/'.$placement.'-'.bin2hex(random_bytes(8)).'.'.$ext;
        $disk->put($path, $contents);

        // Replace, don't accumulate: drop the previous upload if there was one.
        // Only ever delete inside our own directory: settings are writable by
        // any admin through /api/settings, so a hand-edited _icon_path must
        // not be able to point this delete at some other asset (the logo,
        // another extension's files, ...).
        $old = (string) $this->settings->get($prefix.'_icon_path');
        if ($old !== '' && $old !== $path && str_starts_with($old, 'linkrobins-discussion-banners/')) {
            try {
                $disk->delete($old);
            } catch (\Throwable $e) {
                // A stale orphan file is not worth failing the upload over.
            }
        }

        $this->settings->set($prefix.'_icon_path', $path);
        $this->settings->set($prefix.'_icon_type', 'image');

        $url = $disk->url($path);
        $this->settings->set($prefix.'_icon_url', $url);

        return new JsonResponse(['url' => $url, 'path' => $path]);
    }
}
