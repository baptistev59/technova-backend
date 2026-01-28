<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\Filesystem\Filesystem;

final class ImageProxyController extends AbstractController
{
    private string $cacheDir;

    public function __construct(private readonly HttpClientInterface $httpClient, string $cacheDir, private readonly \App\Service\MissingImageLogger $logger)
    {
        $this->cacheDir = $cacheDir;
    }

    #[Route('/_image_proxy', name: 'image_proxy')]
    public function proxy(Request $request): Response
    {
        $url = (string) $request->query->get('u', '');
        if ('' === $url) {
            return new Response('Missing url', Response::HTTP_BAD_REQUEST);
        }

        $parsed = parse_url($url);
        if (false === $parsed || !isset($parsed['host'])) {
            return new Response('Invalid url', Response::HTTP_BAD_REQUEST);
        }

        $allowedHosts = [
            'images.unsplash.com',
        ];

        if (!in_array($parsed['host'], $allowedHosts, true)) {
            return new Response('Host not allowed', Response::HTTP_FORBIDDEN);
        }

        // optional transform params
        $w = $request->query->getInt('w') ?: null;
        $h = $request->query->getInt('h') ?: null;
        $fit = $request->query->get('fit', 'contain');

        // build cache key
        $key = hash('sha256', $url.'|w='.($w?:'').'|h='.($h?:'').'|fit='.$fit);
        $cacheDir = rtrim($this->cacheDir, '/').'/image_proxy';
        $fs = new Filesystem();
        if (!$fs->exists($cacheDir)) {
            $fs->mkdir($cacheDir, 0755);
        }

        $cachedPath = $cacheDir.'/'.substr($key, 0, 2).'/'.$key;
        $cachedDir = dirname($cachedPath);
        if (!$fs->exists($cachedDir)) {
            $fs->mkdir($cachedDir, 0755);
        }

        // return cached if exists
        if (is_file($cachedPath)) {
            $contentType = mime_content_type($cachedPath) ?: 'application/octet-stream';
            $content = file_get_contents($cachedPath);
            return new Response($content, 200, [
                'Content-Type' => $contentType,
                'Cache-Control' => 'public, max-age=86400',
                'Access-Control-Allow-Origin' => '*',
            ]);
        }

        try {
            $resp = $this->httpClient->request('GET', $url, ['timeout' => 10]);
        } catch (\Throwable $e) {
            return new Response('Upstream request failed', Response::HTTP_BAD_GATEWAY);
        }

        $status = $resp->getStatusCode();
        if (200 !== $status) {
            // log the upstream error
            try {
                $this->logger->log($url, $status);
            } catch (\Throwable $e) {
                // do not break image flow if logging fails
            }

            // return local placeholder if available to avoid broken images
            $placeholder = dirname(__DIR__, 2).'/public/images/placeholder-product.png';
            if (is_file($placeholder)) {
                $content = file_get_contents($placeholder);
                return new Response($content, 200, [
                    'Content-Type' => 'image/png',
                    'Cache-Control' => 'public, max-age=3600',
                    'Access-Control-Allow-Origin' => '*',
                ]);
            }

            return new Response('', $status);
        }

        $headers = $resp->getHeaders(false);
        $contentType = $headers['content-type'][0] ?? 'application/octet-stream';
        $body = $resp->getContent();

        // If no transform requested, cache raw body
        if (null === $w && null === $h) {
            file_put_contents($cachedPath, $body);
            return new Response($body, 200, [
                'Content-Type' => $contentType,
                'Cache-Control' => 'public, max-age=86400',
                'Access-Control-Allow-Origin' => '*',
            ]);
        }

        // transform using GD
        $img = @imagecreatefromstring($body);
        if (false === $img) {
            // fallback: store original
            file_put_contents($cachedPath, $body);
            return new Response($body, 200, [
                'Content-Type' => $contentType,
                'Cache-Control' => 'public, max-age=86400',
                'Access-Control-Allow-Origin' => '*',
            ]);
        }

        $origW = imagesx($img);
        $origH = imagesy($img);

        $targetW = $w ?? $origW;
        $targetH = $h ?? $origH;

        if ($fit === 'cover') {
            // scale and crop center to fill
            $ratioSrc = $origW / $origH;
            $ratioDst = $targetW / $targetH;
            if ($ratioSrc > $ratioDst) {
                // source wider -> crop sides
                $newH = $origH;
                $newW = (int) round($origH * $ratioDst);
                $srcX = (int) round(($origW - $newW) / 2);
                $srcY = 0;
            } else {
                // source taller -> crop top/bottom
                $newW = $origW;
                $newH = (int) round($origW / $ratioDst);
                $srcX = 0;
                $srcY = (int) round(($origH - $newH) / 2);
            }
            $tmp = imagecreatetruecolor($targetW, $targetH);
            imagecopyresampled($tmp, $img, 0, 0, $srcX, $srcY, $targetW, $targetH, $newW, $newH);
        } else {
            // contain: scale to fit within box preserving aspect
            $scale = min($targetW / $origW, $targetH / $origH);
            $destW = max(1, (int) round($origW * $scale));
            $destH = max(1, (int) round($origH * $scale));
            $tmp = imagecreatetruecolor($destW, $destH);
            imagecopyresampled($tmp, $img, 0, 0, 0, 0, $destW, $destH, $origW, $origH);
        }

        // encode as JPEG to reduce size
        ob_start();
        imagejpeg($tmp, null, 85);
        $out = ob_get_clean();
        imagedestroy($img);
        imagedestroy($tmp);

        file_put_contents($cachedPath, $out);

        return new Response($out, 200, [
            'Content-Type' => 'image/jpeg',
            'Cache-Control' => 'public, max-age=86400',
            'Access-Control-Allow-Origin' => '*',
        ]);
    }
}
