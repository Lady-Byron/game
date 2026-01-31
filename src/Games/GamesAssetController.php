<?php
 
namespace LadyByron\Game\Games;
 
use Flarum\Foundation\Paths;
use Illuminate\Support\Arr;
use Laminas\Diactoros\Response;
use Laminas\Diactoros\Response\HtmlResponse;
use Laminas\Diactoros\Stream;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
 
final class GamesAssetController implements RequestHandlerInterface
{
    private const ALLOWED_EXTS = [
        'css','js','json','map','html',
        'png','jpg','jpeg','gif','svg','webp',
        'mp3','ogg','wav','m4a','aac','flac','webm','mp4',
        'woff','woff2','ttf','otf','eot',
    ];
 
    private const MIME = [
        'css'  => 'text/css; charset=UTF-8',
        'js'   => 'application/javascript',
        'json' => 'application/json; charset=UTF-8',
        'map'  => 'application/json; charset=UTF-8',
        'html' => 'text/html; charset=UTF-8',
        'png'  => 'image/png',
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif'  => 'image/gif',
        'svg'  => 'image/svg+xml',
        'webp' => 'image/webp',
        'mp3'  => 'audio/mpeg',
        'ogg'  => 'audio/ogg',
        'wav'  => 'audio/wav',
        'm4a'  => 'audio/mp4',
        'aac'  => 'audio/aac',
        'flac' => 'audio/flac',
        'webm' => 'video/webm',
        'mp4'  => 'video/mp4',
        'woff' => 'font/woff',
        'woff2'=> 'font/woff2',
        'ttf'  => 'font/ttf',
        'otf'  => 'font/otf',
        'eot'  => 'application/vnd.ms-fontobject',
    ];
 
    public function __construct(
        private Paths $paths
    ) {}
 
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $route = $request->getAttribute('routeParameters') ?? [];
        $path  = (string) ($route['path'] ?? '');
        $path  = ltrim(str_replace('\\', '/', rawurldecode($path)), '/');
 
        if ($path === '' || str_contains($path, '..')) {
            return new HtmlResponse('Invalid path', 400);
        }
 
        $base = realpath($this->paths->storage . DIRECTORY_SEPARATOR . 'games');
        if ($base === false) {
            return new HtmlResponse('Asset not found', 404);
        }
 
        $full = realpath($base . DIRECTORY_SEPARATOR . $path);
        if ($full === false || strpos($full, $base . DIRECTORY_SEPARATOR) !== 0 || !is_file($full) || !is_readable($full)) {
            return new HtmlResponse('Asset not found', 404);
        }
 
        $ext = strtolower(pathinfo($full, PATHINFO_EXTENSION));
        if (!in_array($ext, self::ALLOWED_EXTS, true)) {
            return new HtmlResponse('Asset not allowed', 404);
        }
 
        $mime    = self::MIME[$ext] ?? 'application/octet-stream';
        $stream  = new Stream($full, 'r');
        $headers = [
            'Content-Type'           => $mime,
            'Cache-Control'          => 'public, max-age=31536000, immutable',
            'Last-Modified'          => gmdate('D, d M Y H:i:s', filemtime($full)) . ' GMT',
            'ETag'                   => '"' . md5_file($full) . '"',
            'X-Content-Type-Options' => 'nosniff',
        ];
 
        return new Response($stream, 200, $headers);
    }
}
