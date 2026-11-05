<?php

namespace App\Http\Middleware;

use App\Support\Security\ExecutableFileDetector;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Symfony\Component\HttpFoundation\Response;

/**
 * Turns away any upload that is, or carries, server-side code: a .php (or
 * similar) name, a server config file such as .htaccess, a file the server
 * would treat as PHP, or PHP code hidden inside an otherwise ordinary file
 * (a "polyglot" image). AffilStack never needs one, and a web shell is
 * exactly what an attacker uploads to keep control of a server. Every form
 * and API route goes through this, so future upload fields are covered
 * without anyone remembering to.
 */
class RejectExecutableUploads
{
    public function handle(Request $request, Closure $next): Response
    {
        foreach ($this->files($request->allFiles()) as $file) {
            if ($this->isExecutable($file)) {
                $message = 'That file type can\'t be uploaded. Upload an image, document or data file instead.';

                return $request->expectsJson() || ! $request->hasSession()
                    ? response()->json(['message' => $message, 'errors' => ['file' => [$message]]], 422)
                    : back()->withErrors(['file' => $message])->withInput($request->except(array_keys($request->allFiles())));
            }
        }

        return $next($request);
    }

    public function isExecutable(UploadedFile $file): bool
    {
        if (ExecutableFileDetector::nameIsExecutable($file->getClientOriginalName())) {
            return true;
        }

        if (ExecutableFileDetector::extensionIsExecutable($file->guessExtension())) {
            return true;
        }

        $path = $file->getRealPath();

        return $path !== false && ExecutableFileDetector::containsPhp($path);
    }

    /**
     * @param  array<mixed>  $files
     * @return iterable<UploadedFile>
     */
    protected function files(array $files): iterable
    {
        foreach ($files as $file) {
            if ($file instanceof UploadedFile) {
                yield $file;
            } elseif (is_array($file)) {
                yield from $this->files($file);
            }
        }
    }
}
