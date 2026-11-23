<?php

namespace App\Providers;

use App\Catalogue\Contracts\ProductRepository;
use App\Catalogue\Repositories\FakeStoreProductRepository;
use App\Catalogue\Repositories\FixtureProductRepository;
use App\Catalogue\TopRatedSelector;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;

/**
 * Chooses the product data source. This is the only place that knows which
 * concrete repository is in use; swapping sources is a config change here.
 */
class CatalogueServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ProductRepository::class, function (Application $app) {
            $config = $app['config']['catalogue'];

            return match ($config['source']) {
                'fakestore' => new FakeStoreProductRepository(
                    http: $app->make(Factory::class),
                    cache: $app->make('cache.store'),
                    baseUrl: $config['fakestore']['base_url'],
                    timeoutSeconds: $config['fakestore']['timeout'],
                    cacheTtlSeconds: $config['fakestore']['cache_ttl'],
                ),
                'fixture' => new FixtureProductRepository($config['fixture_path']),
                default => throw new InvalidArgumentException("Unknown PRODUCT_SOURCE [{$config['source']}]."),
            };
        });

        $this->app->singleton(TopRatedSelector::class, fn (Application $app) => new TopRatedSelector(
            products: $app->make(ProductRepository::class),
            featuredIds: $app['config']['catalogue.featured_ids'],
            categoryTopRatedCount: $app['config']['catalogue.category_top_rated_count'],
        ));
    }
}
