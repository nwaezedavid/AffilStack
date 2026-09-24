<?php

namespace App\Logging;

use Illuminate\Log\Logger;

/**
 * Laravel "log tap": wired onto every real log sink in config/logging.php
 * (the 'tap' key on single, daily, monthly, slack, papertrail, stderr,
 * syslog, and errorlog) so every handler runs the same credential-scrubbing
 * processor, regardless of which channel(s) a given log call ends up
 * flowing through via the 'stack' driver — 'stack' merges each of its named
 * channels' own handlers *and processors*, so tapping the leaf channels
 * here is what protects both direct channel use and the stack.
 */
class RedactsSecrets
{
    public function __invoke(Logger $logger): void
    {
        $logger->pushProcessor(new SecretRedactionProcessor($this->knownSecretValues()));
    }

    /**
     * Every string value currently loaded into config() under a
     * credential-shaped key, collected once per log-manager boot. Walking
     * config() rather than hardcoding env var names here means a newly
     * added integration's key is covered automatically the moment its
     * config file does `env('THE_NEW_KEY')` — nothing to remember to wire
     * up in this file as the platform grows.
     *
     * @return string[]
     */
    protected function knownSecretValues(): array
    {
        $values = [];

        $this->walk(config()->all(), $values);

        return array_values(array_unique(array_filter(
            $values,
            fn ($value) => is_string($value) && strlen($value) >= 6
        )));
    }

    /**
     * @param  array<array-key, mixed>  $config
     * @param  string[]  $values
     */
    protected function walk(array $config, array &$values): void
    {
        foreach ($config as $key => $value) {
            if (is_array($value)) {
                $this->walk($value, $values);

                continue;
            }

            if (is_string($key) && is_string($value) && preg_match(SecretRedactionProcessor::SECRET_KEY_PATTERN, $key)) {
                $values[] = $value;
            }
        }
    }
}
