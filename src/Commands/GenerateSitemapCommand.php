<?php

declare(strict_types=1);

namespace Hojabbr\LaravelAutoSitemap\Commands;

use Hojabbr\LaravelAutoSitemap\Contracts\SitemapGenerator;
use Illuminate\Console\Command;

class GenerateSitemapCommand extends Command
{
    protected $signature = 'sitemap:generate {--model= : Generate sitemap for specific model}';

    protected $description = 'Generate XML sitemaps for your Laravel application';

    public function handle(SitemapGenerator $generator): int
    {
        $this->info('Starting sitemap generation...');

        $modelKey = $this->option('model');

        if ($modelKey) {
            if (! isset(config('sitemap.models')[$modelKey])) {
                $this->error("Model key '{$modelKey}' not found in sitemap config");

                return 1;
            }

            $generator->generateForModel($modelKey);
            $this->info("Generated sitemap for model: {$modelKey}");
        } else {
            $generator->generate();
            $this->info('Generated all sitemaps successfully');
        }

        return 0;
    }
}
