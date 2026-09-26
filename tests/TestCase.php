<?php

namespace Tests;

use App\Support\OutboundUrlGuard;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // No real DNS lookups in tests: every hostname resolves to a public
        // documentation address unless a test says otherwise.
        OutboundUrlGuard::resolveUsing(fn (string $host): array => ['93.184.216.34']);

        // A test must never reach a real payment gateway, AI provider, or any
        // other live API: any request not matched by an Http::fake() fails.
        Http::preventStrayRequests();
    }

    protected function tearDown(): void
    {
        OutboundUrlGuard::resolveUsing(null);

        parent::tearDown();
    }
}
