<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Sitemap Settings
    |--------------------------------------------------------------------------
    */
    'filename' => 'sitemap',
    'items_per_page' => 1000, // Number of items per sitemap file

    /*
    |--------------------------------------------------------------------------
    | Storage Settings
    |--------------------------------------------------------------------------
    */
    'storage' => [
        'disk' => 'public', // Storage disk to use
        'path' => 'sitemaps', // Path within the disk
    ],

    /*
    |--------------------------------------------------------------------------
    | Localization Settings
    |--------------------------------------------------------------------------
    */
    'locales' => [
        'enabled' => true, // Enable/disable multilingual support
        'default_locale' => 'en',
        'supported_locales' => ['en', 'fr'], // List of supported locales
        'hide_default_locale' => true, // Whether to hide default locale from URLs
    ],

    /*
    |--------------------------------------------------------------------------
    | Models Configuration
    |--------------------------------------------------------------------------
    | Each model configuration can include:
    | - model: The model class (required)
    | - route: The route name for generating URLs (required)
    | - parameters: Route parameters array (required)
    | - frequency: Update frequency (optional)
    | - priority: URL priority (optional)
    | - conditions: Query conditions (optional)
    | - multilingual: Whether the model has translations (optional)
    | - lastmod_field: Field to use for last modified date (optional)
    */
    'models' => [
        'categories' => [
            'model' => \App\Models\Category::class,
            'route' => 'categories.show',
            'parameters' => ['category:slug'],
            'frequency' => 'daily',
            'priority' => '0.8',
            'conditions' => ['is_published' => true],
            'multilingual' => false, // Set false for non-translatable models
            'lastmod_field' => 'updated_at',
        ],
        'posts' => [
            'model' => \App\Models\Post::class,
            'route' => 'posts.show',
            'parameters' => ['post:slug'],
            'frequency' => 'daily',
            'priority' => '0.8',
            'conditions' => ['is_published' => true],
            'multilingual' => true, // Set true for models with translations
            'lastmod_field' => 'updated_at',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Static Pages
    |--------------------------------------------------------------------------
    | Configuration for static pages in your sitemap
    */
    'static_pages' => [
        [
            'url' => '/',
            'priority' => '1.0',
            'frequency' => 'daily',
            'multilingual' => true,
        ],
        // Add more static pages as needed
    ],
];
