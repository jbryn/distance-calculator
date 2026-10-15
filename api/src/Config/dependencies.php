<?php

declare(strict_types=1);

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\HttpFactory;
use Jbryn\Downriver\Config\Settings;
use Jbryn\Downriver\Overpass\CachedOverpassClient;
use Jbryn\Downriver\Overpass\HttpOverpassClient;
use Jbryn\Downriver\Overpass\OfflineOverpassClient;
use Jbryn\Downriver\Overpass\OverpassClient;
use Jbryn\Downriver\Overpass\QueryCache;
use Jbryn\Downriver\River\RiverCatalogue;

return [
    RiverCatalogue::class => static fn(Settings $settings): RiverCatalogue => RiverCatalogue::fromFile(
        $settings->riverCatalogueFile,
    ),

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
