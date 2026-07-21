<?php

namespace LinkRobins\DiscussionBanners;

use Flarum\Foundation\ValidationException;
use Flarum\Http\RequestUtil;
use Illuminate\Contracts\Filesystem\Factory;
use Illuminate\Support\Arr;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UploadedFileInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * POST /api/linkrobins-discussion-banners/{banner}/icon
 *
 * Admin-only multipart upload of a banner icon image. Stored on the
 * flarum-assets disk (multi-server / CDN safe) and the path is handed back to
 * the admin page, which saves it with the rest of the banner; the public URL
 * is resolved from that path at read time. Nothing is written to settings
 * here, so an upload the admin never saves simply stays unreferenced and is
 * cleaned up by PruneIcons on the next save.
 *
 * Raster formats only: an <img> never executes SVG scripts, but there is no
 * reason to store markup as an icon either.
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
        protected Factory $filesystem,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        RequestUtil::getActor($request)->assertAdmin();

        // The banner id only ever names the file, but it comes from the URL,
        // so it is restricted to the same characters BannerSettings allows.
        $banner = (string) Arr::get($request->getQueryParams(), 'banner');
        if (! preg_match('/^[A-Za-z0-9_-]{1,32}$/', $banner)) {
            throw new ValidationException(['icon' => 'Unknown banner.']);
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

        $disk = $this->filesystem->disk('flarum-assets');
        $path = BannerSettings::ICON_DIR.$banner.'-'.bin2hex(random_bytes(8)).'.'.$ext;
        $disk->put($path, $contents);

        return new JsonResponse(['path' => $path, 'url' => $disk->url($path)]);
    }
}
