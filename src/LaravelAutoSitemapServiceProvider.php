<?php

declare(strict_types=1);

namespace Hojabbr\LaravelAutoSitemap;

use Hojabbr\LaravelAutoSitemap\Commands\GenerateSitemapCommand;
use Hojabbr\LaravelAutoSitemap\Contracts\SitemapGenerator;
use Hojabbr\LaravelAutoSitemap\Generators\XmlSitemapGenerator;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\App;

class LaravelAutoSitemapServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/sitemap.php',
            'sitemap'
        );

        App::bind(SitemapGenerator::class, function ($app) {
            return new XmlSitemapGenerator(
                config: $app['config']['sitemap'],
                filesystem: $app['filesystem']
            );
        });
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/sitemap.php' => config_path('sitemap.php'),
            ], 'sitemap-config');

            $this->commands([
                GenerateSitemapCommand::class,
            ]);
        }
    }
}
