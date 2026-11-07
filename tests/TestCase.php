<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Any real outgoing HTTP request fails the test. The AI connectors talk
        // over HTTP; a test that forgot to fake one must not reach a paid API.
        // Tests that fake responses with Http::fake() are unaffected.
        Http::preventStrayRequests();
    }
}
