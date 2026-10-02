<?php

namespace App\Http\Controllers;

use App\Services\Seo\FaviconGenerator;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Throwable;

/**
 * Serves the generated favicon set from stable root URLs — /favicon.ico,
 * /favicon-48x48.png, /apple-touch-icon.png, /site.webmanifest, ... — the
 * exact places browsers and Googlebot-Image look, on the same host as the
 * homepage, independent of the /storage symlink or the host's web-root
 * layout. See FaviconGenerator for how the set is built.
 *
 * Builds the set on the spot if it is missing or stale (first request
 * after a deploy, or after the icon was changed outside the admin form).
 * Versioned requests (?v=...) are cached for a year — the version changes
 * whenever the icon does — and the bare /favicon.ico browsers request on
 * their own for a day.
 */
class FaviconController extends Controller
{
    public function __construct(protected FaviconGenerator $favicons) {}

    public function show(Request $request, string $file): Response
    {
        abort_unless(array_key_exists($file, FaviconGenerator::FILES), 404);

        try {
            $this->favicons->ensureFresh();
        } catch (Throwable $e) {
            report($e);
        }

        $contents = $this->favicons->contents($file);

        abort_if($contents === null, 404);

        $etag = '"'.md5($contents).'"';

        $cacheControl = $request->query('v')
            ? 'public, max-age=31536000, immutable'
            : 'public, max-age=86400';

        if (trim((string) $request->header('If-None-Match')) === $etag) {
            return response('', 304)->header('ETag', $etag)->header('Cache-Control', $cacheControl);
        }

        return response($contents, 200, [
            'Content-Type' => FaviconGenerator::FILES[$file],
            'Cache-Control' => $cacheControl,
            'ETag' => $etag,
            'X-Robots-Tag' => 'all',
        ]);
    }
}
