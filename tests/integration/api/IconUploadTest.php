<?php

/*
 * This file is part of linkrobins/discussion-banners.
 *
 * For detailed copyright and license information, please view the
 * LICENSE file that was distributed with this source code.
 */

namespace LinkRobins\DiscussionBanners\Tests\integration\api;

use Flarum\Testing\integration\RetrievesAuthorizedUsers;
use Flarum\Testing\integration\TestCase;
use Laminas\Diactoros\Stream;
use Laminas\Diactoros\UploadedFile;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ServerRequestInterface;

class IconUploadTest extends TestCase
{
    use RetrievesAuthorizedUsers;

    // A real 1x1 transparent PNG, so finfo detects image/png from the bytes.
    private const PNG_BASE64 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';

    public function setUp(): void
    {
        parent::setUp();

        $this->extension('linkrobins-discussion-banners');

        $this->prepareDatabase([
            'users' => [
                $this->normalUser(), // id 2
            ],
        ]);
    }

    private function uploadRequest(?int $actorId, string $placement, string $bytes, string $clientName): ServerRequestInterface
    {
        $stream = new Stream('php://temp', 'wb+');
        $stream->write($bytes);
        $stream->rewind();

        $options = $actorId ? ['authenticatedAs' => $actorId] : [];

        return $this->request('POST', '/api/linkrobins-discussion-banners/'.$placement.'/icon', $options)
            ->withUploadedFiles(['icon' => new UploadedFile($stream, strlen($bytes), UPLOAD_ERR_OK, $clientName, 'image/png')]);
    }

    #[Test]
    public function guests_cannot_upload(): void
    {
        $response = $this->send($this->uploadRequest(null, 'top', base64_decode(self::PNG_BASE64), 'icon.png'));

        // 400 = the CSRF middleware rejects the unauthenticated write before
        // the controller even runs; 401/403 would be the controller's own
        // rejection. All three mean "no".
        $this->assertContains($response->getStatusCode(), [400, 401, 403]);
    }

    #[Test]
    public function regular_users_cannot_upload(): void
    {
        $response = $this->send($this->uploadRequest(2, 'top', base64_decode(self::PNG_BASE64), 'icon.png'));

        $this->assertEquals(403, $response->getStatusCode());
    }

    #[Test]
    public function admins_can_upload_a_png_and_the_settings_point_at_it(): void
    {
        $response = $this->send($this->uploadRequest(1, 'top', base64_decode(self::PNG_BASE64), 'icon.png'));

        $this->assertEquals(200, $response->getStatusCode());

        $body = json_decode($response->getBody()->getContents(), true);
        $this->assertStringStartsWith('linkrobins-discussion-banners/top-', $body['path']);
        $this->assertStringEndsWith('.png', $body['path']);

        $settings = $this->database()->table('settings')->pluck('value', 'key');
        $this->assertSame($body['path'], $settings['linkrobins-discussion-banners.top_icon_path']);
        $this->assertSame('image', $settings['linkrobins-discussion-banners.top_icon_type']);
    }

    #[Test]
    public function file_bytes_decide_the_type_not_the_client_name(): void
    {
        // Plain text with a .png name and image/png client mime: rejected.
        $response = $this->send($this->uploadRequest(1, 'top', 'just some text', 'icon.png'));

        $this->assertEquals(422, $response->getStatusCode());
    }

    #[Test]
    public function unknown_placements_are_rejected(): void
    {
        $response = $this->send($this->uploadRequest(1, 'sidebar', base64_decode(self::PNG_BASE64), 'icon.png'));

        $this->assertEquals(422, $response->getStatusCode());
    }
}
