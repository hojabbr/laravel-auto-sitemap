<?php

declare(strict_types=1);

namespace Hojabbr\LaravelAutoSitemap\Generators;

use Hojabbr\LaravelAutoSitemap\Contracts\SitemapGenerator;
use Illuminate\Contracts\Filesystem\Factory as Filesystem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\URL;
use LaravelLocalization;
use XMLWriter;

class XmlSitemapGenerator implements SitemapGenerator
{
    private XMLWriter $writer;

    private array $sitemapFiles = [];

    private int $currentItemCount = 0;

    private string $currentSitemapKey;

    public function __construct(
        private readonly array $config,
        private readonly Filesystem $filesystem
    ) {
        $this->writer = new XMLWriter;
    }

    public function generate(): void
    {
        // Generate model sitemaps
        foreach ($this->config['models'] as $modelKey => $modelConfig) {
            $this->generateModelSitemap($modelKey, $modelConfig);
        }

        // Generate static pages sitemap
        $this->generateStaticSitemap();

        // Generate index sitemap
        $this->generateIndex();
    }

    private function generateModelSitemap(string $key, array $modelConfig): void
    {
        $model = app($modelConfig['model']);
        $query = $model->query();

        // Apply conditions if specified
        if (isset($modelConfig['conditions'])) {
            foreach ($modelConfig['conditions'] as $column => $value) {
                $query->where($column, $value);
            }
        }

        // Get all models
        $models = $query->get();
        
        // For multilingual models, generate sitemap for each locale
        if ($modelConfig['multilingual'] && $this->config['locales']['enabled']) {
            foreach ($this->config['locales']['supported_locales'] as $locale) {
                if ($models->isEmpty()) {
                    continue;
                }

                // Set locale for URL generation
                App::setLocale($locale);
                
                // Filter models for this locale and chunk them
                $localeModels = $models->filter(function ($model) use ($locale) {
                    if ($locale === $this->config['locales']['default_locale']) {
                        return true;
                    }
                    return method_exists($model, 'translations') && 
                           $model->translations()->where('locale', $locale)->exists();
                });

                if ($localeModels->isEmpty()) {
                    continue;
                }

                $chunks = $localeModels->chunk($this->config['items_per_page']);
                $pageNumber = 1;
                
                foreach ($chunks as $chunk) {
                    // Start new sitemap for this chunk
                    $sitemapKey = $this->config['locales']['hide_default_locale'] && $locale === $this->config['locales']['default_locale']
                        ? "{$key}-{$pageNumber}"
                        : "{$locale}-{$key}-{$pageNumber}";
                    
                    $this->startNewSitemap($sitemapKey);

                    foreach ($chunk as $model) {
                        $this->addModelUrlToSitemap($model, $modelConfig);
                    }

                    $this->finishCurrentSitemap();
                    $pageNumber++;
                }
            }
        } else {
            // For non-multilingual models, chunk and generate sitemaps
            if ($models->isEmpty()) {
                return;
            }

            $chunks = $models->chunk($this->config['items_per_page']);
            $pageNumber = 1;
            
            foreach ($chunks as $chunk) {
                $this->startNewSitemap("{$key}-{$pageNumber}");
                
                foreach ($chunk as $model) {
                    $this->addModelUrlToSitemap($model, $modelConfig);
                }

                $this->finishCurrentSitemap();
                $pageNumber++;
            }
        }
    }

    private function buildModelQuery(Model $model, array $conditions): Builder
    {
        $query = $model->query();

        foreach ($conditions as $field => $value) {
            $query->where($field, $value);
        }

        return $query;
    }

    private function startNewSitemap(string $key): void
    {
        $this->currentItemCount = 0;
        $this->currentSitemapKey = $key;
        
        $this->writer = new XMLWriter;
        $this->writer->openMemory();
        $this->writer->startDocument('1.0', 'UTF-8');
        $this->writer->startElement('urlset');
        $this->writer->writeAttribute('xmlns', 'http://www.sitemaps.org/schemas/sitemap/0.9');
        $this->writer->writeAttribute('xmlns:xhtml', 'http://www.w3.org/1999/xhtml');
    }

    private function finishCurrentSitemap(): void
    {
        if (!$this->writer) {
            return;
        }

        $this->writer->endElement(); // Close urlset
        
        $filename = $this->currentSitemapKey . '.xml';
        $this->sitemapFiles[] = $filename;
        
        $this->filesystem->disk($this->config['storage']['disk'])
            ->put(
                $this->config['storage']['path'] . '/' . $filename,
                $this->writer->outputMemory()
            );
    }

    private function addModelUrlToSitemap(Model $model, array $modelConfig): void
    {
        // Set locale before building parameters to ensure correct accessors are used
        $currentLocale = App::getLocale();
        $originalLocale = $currentLocale;
        
        // Generate URL based on route parameters
        $parameters = $this->buildUrlParameters($model, $modelConfig['parameters']);
        
        // Skip if we don't have any parameters (indicates missing translation)
        if (empty($parameters)) {
            return;
        }

        $this->writer->startElement('url');
        
        // Check locale settings
        $isDefaultLocale = $currentLocale === $this->config['locales']['default_locale'];
        $hideDefaultLocale = $this->config['locales']['hide_default_locale'] ?? false;
        
        // Generate URL with or without locale prefix based on settings
        if ($modelConfig['multilingual']) {
            if ($hideDefaultLocale && $isDefaultLocale) {
                // For default locale when hiding, don't use locale prefix
                $url = route($modelConfig['route'], $parameters, true);
            } else {
                // For non-default locales or when showing all locales, force locale prefix
                $url = LaravelLocalization::getLocalizedURL($currentLocale, route($modelConfig['route'], $parameters, false), [], true);
            }
        } else {
            // For non-multilingual models, never use locale prefix
            App::setLocale($this->config['locales']['default_locale']);
            $url = route($modelConfig['route'], $parameters, true);
        }
        
        // Restore original locale
        App::setLocale($originalLocale);
        
        $this->writer->writeElement('loc', $url);

        if (isset($modelConfig['lastmod_field']) && isset($model->{$modelConfig['lastmod_field']})) {
            $this->writer->writeElement('lastmod', $model->{$modelConfig['lastmod_field']}->toAtomString());
        }

        $this->writer->writeElement('changefreq', $modelConfig['frequency'] ?? 'weekly');
        $this->writer->writeElement('priority', $modelConfig['priority'] ?? '0.5');

        $this->writer->endElement();
    }

    private function buildUrlParameters(Model $model, array $parameterNames): array
    {
        $currentLocale = App::getLocale();
        $modelConfig = $this->getModelConfigForModel($model);
        
        // For non-default locale, ensure we have a translation
        if ($currentLocale !== $this->config['locales']['default_locale'] && 
            ($modelConfig['multilingual'] ?? false) && 
            method_exists($model, 'translations')) {
            
            $hasTranslation = $model->translations()->where('locale', $currentLocale)->exists();
            if (!$hasTranslation) {
                return [];
            }
        }

        return collect($parameterNames)->mapWithKeys(function ($param) use ($model, $currentLocale) {
            // Handle route model binding with custom keys (e.g., {category:slug})
            if (str_contains($param, ':')) {
                [$modelParam, $field] = explode(':', $param);
                
                // For slug field, use localizedSlug if available
                if ($field === 'slug' && method_exists($model, 'localizedSlug')) {
                    return [$modelParam => $model->localizedSlug($currentLocale)];
                }
                
                // Use the model's accessor if it exists
                return [$modelParam => $model->{$field}];
            }
            
            // If the parameter name matches the model's base name, use the model's ID
            if (strtolower(class_basename($model)) === strtolower($param)) {
                return [$param => $model->id];
            }

            // If parameter is 'slug', use localizedSlug if available
            if ($param === 'slug' && method_exists($model, 'localizedSlug')) {
                return ['slug' => $model->localizedSlug($currentLocale)];
            }
            
            // Use the model's accessor
            return [$param => $model->{$param}];
        })->filter()->toArray(); // Remove any empty entries
    }

    private function getModelConfigForModel(Model $model): array
    {
        foreach ($this->config['models'] as $modelConfig) {
            if ($model instanceof $modelConfig['model']) {
                return $modelConfig;
            }
        }
        return [];
    }

    private function addStaticUrlToSitemap(array $page, ?string $locale = null): void
    {
        $this->writer->startElement('url');
        
        // Check locale settings
        $hideDefaultLocale = $this->config['locales']['hide_default_locale'] ?? false;
        $isDefaultLocale = $locale === $this->config['locales']['default_locale'];
        
        // Store original locale
        $originalLocale = App::getLocale();
        
        // Build URL based on locale and hide default locale setting
        if ($page['multilingual']) {
            if ($hideDefaultLocale && $isDefaultLocale) {
                // For default locale when hiding, don't use locale prefix
                $url = url($page['url']);
            } else {
                // For non-default locales or when showing all locales, force locale prefix
                App::setLocale($locale);
                $url = LaravelLocalization::getLocalizedURL($locale, url($page['url']), [], true);
            }
        } else {
            // For non-multilingual pages, never use locale prefix
            $url = url($page['url']);
        }
        
        // Restore original locale
        App::setLocale($originalLocale);
        
        $this->writer->writeElement('loc', $url);
        
        if (isset($page['lastmod'])) {
            $this->writer->writeElement('lastmod', $page['lastmod']);
        }
        
        $this->writer->writeElement('changefreq', $page['frequency'] ?? 'weekly');
        $this->writer->writeElement('priority', $page['priority'] ?? '0.5');

        $this->writer->endElement();
    }

    private function generateStaticSitemap(): void
    {
        if (empty($this->config['static_pages'])) {
            return;
        }

        // For multilingual static pages, generate sitemap for each locale
        if ($this->config['locales']['enabled']) {
            foreach ($this->config['locales']['supported_locales'] as $locale) {
                $hasMultilingualPages = false;
                $multilingualPages = [];
                
                // Collect multilingual pages
                foreach ($this->config['static_pages'] as $page) {
                    if ($page['multilingual'] ?? false) {
                        $hasMultilingualPages = true;
                        $multilingualPages[] = $page;
                    }
                }
                
                if (!$hasMultilingualPages) {
                    continue;
                }

                // Chunk multilingual pages
                $chunks = array_chunk($multilingualPages, $this->config['items_per_page']);
                $pageNumber = 1;
                
                foreach ($chunks as $chunk) {
                    // Set locale for URL generation
                    App::setLocale($locale);
                    
                    // Start new sitemap for this chunk
                    $sitemapKey = $this->config['locales']['hide_default_locale'] && $locale === $this->config['locales']['default_locale']
                        ? "static-{$pageNumber}"
                        : "{$locale}-static-{$pageNumber}";
                    
                    $this->startNewSitemap($sitemapKey);

                    foreach ($chunk as $page) {
                        $this->addStaticUrlToSitemap($page, $locale);
                    }

                    $this->finishCurrentSitemap();
                    $pageNumber++;
                }
            }
        }
        
        // Generate sitemap for non-multilingual pages
        $nonMultilingualPages = [];
        foreach ($this->config['static_pages'] as $page) {
            if (!($page['multilingual'] ?? false)) {
                $nonMultilingualPages[] = $page;
            }
        }
        
        if (!empty($nonMultilingualPages)) {
            $chunks = array_chunk($nonMultilingualPages, $this->config['items_per_page']);
            $pageNumber = 1;
            
            foreach ($chunks as $chunk) {
                $this->startNewSitemap("static-single-{$pageNumber}");
                foreach ($chunk as $page) {
                    $this->addStaticUrlToSitemap($page);
                }
                $this->finishCurrentSitemap();
                $pageNumber++;
            }
        }
    }

    private function generateIndex(): void
    {
        $this->writer = new XMLWriter;
        $this->writer->openMemory();
        $this->writer->startDocument('1.0', 'UTF-8');
        $this->writer->setIndent(true);
        $this->writer->startElement('sitemapindex');
        $this->writer->writeAttribute('xmlns', 'http://www.sitemaps.org/schemas/sitemap/0.9');

        $now = now();

        foreach ($this->sitemapFiles as $filename) {
            $this->writer->startElement('sitemap');
            $this->writer->writeElement('loc', $this->getSitemapUrl($filename));
            $this->writer->writeElement('lastmod', $now->toAtomString());
            $this->writer->endElement();
        }

        $this->writer->endElement();
        
        $this->filesystem->disk($this->config['storage']['disk'])
            ->put(
                $this->config['storage']['path'].'/sitemap.xml',
                $this->writer->outputMemory()
            );
    }

    /**
     * Get the URL for a sitemap file
     */
    public function getSitemapUrl(string $filename): string
    {
        return url('storage/' . $this->config['storage']['path'] . '/' . $filename);
    }
}
