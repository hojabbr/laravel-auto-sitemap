<?php

declare(strict_types=1);

namespace Hojabbr\LaravelAutoSitemap\Contracts;

interface SitemapGenerator
{
    /**
     * Generate all sitemaps
     */
    public function generate(): void;
}
