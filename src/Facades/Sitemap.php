<?php

declare(strict_types=1);

namespace Hojabbr\LaravelAutoSitemap\Facades;

use Hojabbr\LaravelAutoSitemap\Contracts\SitemapGenerator;
use Illuminate\Support\Facades\Facade;

/**
 * @method static void generate()
 * @method static void generateForModel(string $modelKey)
 * @method static void generateStaticPages()
 * @method static string getSitemapUrl(string $filename)
 *
 * @see \Hojabbr\LaravelAutoSitemap\Contracts\SitemapGenerator
 */
class Sitemap extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return SitemapGenerator::class;
    }
}
