<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Tests never reach the internet. Loading the homepage used to crawl
        // every SpareRoom advertiser for real (three minutes, on someone
        // else's site); a test that needs a response fakes it.
        Http::preventStrayRequests();
    }
}
