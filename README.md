# Laravel Auto Sitemap

A Laravel package to automatically generate sitemaps for your models and static pages, with full support for multilingual content.

## Features

- 🌐 Full multilingual support with automatic locale handling
- 🔄 Dynamic model-based URLs with translation support
- 📑 Static page URLs with locale variants
- 🎯 Flexible route parameter binding
- 🌍 Smart locale prefix handling
- 🔧 Configurable URL generation
- 📁 Multiple sitemap file support
- 🔍 SEO-friendly output

## Installation

```bash
composer require hojabbr/laravel-auto-sitemap
```

Publish the configuration:

```bash
php artisan vendor:publish --provider="Hojabbr\LaravelAutoSitemap\LaravelAutoSitemapServiceProvider"
```

## Configuration

```php
return [
    'storage' => [
        'path' => 'sitemaps',
        'disk' => 'public',
    ],
    'items_per_page' => 1000,
    'locales' => [
        'enabled' => true,
        'default_locale' => 'en',
        'supported_locales' => ['en', 'de'],
        'hide_default_locale' => true,
    ],
    'models' => [
        'posts' => [
            'model' => \App\Models\Post::class,
            'route' => 'posts.show',
            'parameters' => ['post:slug'], // or ['post', 'slug'] for separate parameters
            'multilingual' => true,
            'conditions' => [
                'is_published' => true
            ],
            'frequency' => 'weekly',
            'priority' => '0.8'
        ],
    ],
    'static_pages' => [
        [
            'url' => '/',
            'frequency' => 'daily',
            'priority' => '1.0',
            'multilingual' => true
        ],
    ]
];
```

## Multilingual Support

The package supports multiple approaches to handling translations:

### 1. Using Laravel Accessors (Recommended)

For the best integration, use Laravel's accessor pattern with a translations relationship:

```php
class Post extends Model
{
    public function translations(): HasMany
    {
        return $this->hasMany(PostTranslation::class);
    }

    // Optional but recommended: Helper method for localized slugs
    public function localizedSlug($locale): string
    {
        $translatedTitle = $this->translations?->where('locale', $locale)->first()?->title;
        return $translatedTitle ? Str::slug($translatedTitle) : $this->attributes['slug'];
    }

    // Accessor for translated title
    public function getTitleAttribute(): string
    {
        return $this->translation?->title ?? $this->attributes['title'];
    }

    // Accessor for translated slug
    public function getSlugAttribute(): string
    {
        return Str::slug($this->translation?->title ?? $this->attributes['title']);
    }
}

class PostTranslation extends Model
{
    protected $fillable = ['locale', 'title', 'slug'];

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }
}
```

### 2. Using Basic Translation Relations

If you prefer a simpler approach without accessors:

```php
class Post extends Model
{
    public function translations(): HasMany
    {
        return $this->hasMany(PostTranslation::class);
    }

    // Optional: Get current locale's translation
    public function translation(): HasOne
    {
        return $this->hasOne(PostTranslation::class)
            ->where('locale', App::getLocale());
    }
}
```

### 3. Using Other Translation Methods

The package can work with other translation approaches as long as:

1. Your model has a `translations()` relation that returns translated content
2. Translations are stored with a `locale` field
3. You either:
   - Use Laravel accessors to get translated values
   - Implement a `localizedSlug()` method for custom slug handling
   - Store translations in a way that can be queried by locale

## URL Generation

### Route Parameters

The package supports two styles of route parameters:

1. Model binding with field:
```php
'parameters' => ['post:slug'] // Uses Route::get('/posts/{post:slug}')
```

2. Separate parameters:
```php
'parameters' => ['post', 'slug'] // Uses Route::get('/posts/{post}/{slug}')
```

### Locale Handling

The package intelligently handles locale prefixes:

```php
'locales' => [
    'enabled' => true,
    'default_locale' => 'en',
    'supported_locales' => ['en', 'de'],
    'hide_default_locale' => true,
]
```

This will generate:
- Default locale (en): `/posts/my-post`
- Other locales (de): `/de/posts/mein-post`

### Translation Fallbacks

The package implements smart fallback behavior:

1. For default locale:
   - Uses model attributes if no translation exists
   - Ensures all content is always available

2. For other locales:
   - Only generates URLs if translations exist
   - Prevents 404s for untranslated content

### Static Pages

Static pages can be multilingual or non-multilingual:

```php
'static_pages' => [
    [
        'url' => 'about-us',
        'multilingual' => true, // Will generate /about-us and /de/about-us
        'frequency' => 'monthly'
    ],
    [
        'url' => 'terms',
        'multilingual' => false, // Will only generate /terms
        'frequency' => 'yearly'
    ]
]
```

## Usage

You can generate sitemaps in two ways:

### 1. Using Artisan Command

```bash
php artisan sitemap:generate
```

This will generate all sitemaps based on your configuration.

### 2. Using the Facade

```php
use Hojabbr\LaravelAutoSitemap\Facades\Sitemap;

Sitemap::generate();
```

Both methods will create:
- A sitemap index at `public/storage/sitemaps/sitemap.xml`
- Individual sitemaps for each model and locale
- Static pages sitemaps

### Output Structure

For a typical setup with multilingual content, you'll get:

```
sitemap.xml (index)
├── posts.xml (or posts-1.xml if paginated)
├── de-posts.xml
├── fr-posts.xml
├── static.xml
├── de-static.xml
└── fr-static.xml
```

If you have more than `items_per_page` items (default: 1000), sitemaps will be paginated:
```
sitemap.xml (index)
├── posts-1.xml
├── posts-2.xml
├── de-posts-1.xml
├── de-posts-2.xml
└── ...
```

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
