<?php

declare(strict_types=1);

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\HttpFactory;
use Jbryn\DistanceCalculator\Config\Settings;
use Jbryn\DistanceCalculator\Overpass\CachedOverpassClient;
use Jbryn\DistanceCalculator\Overpass\HttpOverpassClient;
use Jbryn\DistanceCalculator\Overpass\OfflineOverpassClient;
use Jbryn\DistanceCalculator\Overpass\OverpassClient;
use Jbryn\DistanceCalculator\Overpass\QueryCache;
use Jbryn\DistanceCalculator\Service\DistanceCalculator;
use Jbryn\DistanceCalculator\Service\Validator;

return [
    DistanceCalculator::class => \DI\create(DistanceCalculator::class),
    Validator::class => \DI\create(Validator::class),

    // Saved responses first; the network (or nothing, in offline mode) only on a miss
    OverpassClient::class => static function (Settings $settings): OverpassClient {
        $httpFactory = new HttpFactory();

        $source = $settings->overpassOffline
            ? new OfflineOverpassClient()
            : new HttpOverpassClient(
                new Client(['timeout' => 30, 'connect_timeout' => 5]),
                $httpFactory,
                $httpFactory,
                $settings->overpassEndpoints,
                $settings->overpassUserAgent,
            );

        return new CachedOverpassClient(
            $source,
            new QueryCache($settings->overpassCacheDir),
            $settings->overpassCacheTtl,
        );
    },
];
