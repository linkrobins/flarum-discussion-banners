<?php

namespace LinkRobins\DiscussionBanners;

use Flarum\Foundation\ValidationException;
use Flarum\Http\RequestUtil;
use Flarum\Settings\SettingsRepositoryInterface;
use Illuminate\Contracts\Filesystem\Factory;
use Illuminate\Support\Arr;
use Laminas\Diactoros\Response\EmptyResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * DELETE /api/linkrobins-discussion-banners/{placement}/icon
 *
 * Admin-only: removes a placement's uploaded icon file and clears the icon
 * back to "none".
 */
final class DeleteIconController implements RequestHandlerInterface
{
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

        $prefix = BannerSettings::PREFIX.$placement;

        $old = (string) $this->settings->get($prefix.'_icon_path');
        if ($old !== '') {
            try {
                $this->filesystem->disk('flarum-assets')->delete($old);
            } catch (\Throwable $e) {
                // Clearing the settings still detaches the icon; an orphan
                // file is not worth a 500.
            }
        }

        $this->settings->set($prefix.'_icon_path', '');
        $this->settings->set($prefix.'_icon_url', '');
        $this->settings->set($prefix.'_icon_type', '');

        return new EmptyResponse(204);
    }
}
