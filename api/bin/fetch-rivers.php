<?php

declare(strict_types=1);

/**
 * Downloads the map data for rivers in the catalogue and saves it where the application reads it.
 *
 *   composer rivers:fetch              every river in data/rivers.json
 *   composer rivers:fetch -- 8974372   only the given relation ids
 *
 * A river is fetched, then built from the saved files exactly as the API would build it,
 * so a river that cannot be offered (for example one with gaps) is reported here.
 */

use Dotenv\Dotenv;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\HttpFactory;
use Jbryn\DistanceCalculator\Config\Settings;
use Jbryn\DistanceCalculator\Overpass\CachedOverpassClient;
use Jbryn\DistanceCalculator\Overpass\HttpOverpassClient;
use Jbryn\DistanceCalculator\Overpass\OfflineOverpassClient;
use Jbryn\DistanceCalculator\Overpass\OverpassUnavailableException;
use Jbryn\DistanceCalculator\Overpass\QueryCache;
use Jbryn\DistanceCalculator\River\FeatureLocator;
use Jbryn\DistanceCalculator\River\RiverCatalogue;
use Jbryn\DistanceCalculator\River\RiverDataFetcher;
use Jbryn\DistanceCalculator\River\RiverRepository;
use Jbryn\DistanceCalculator\River\RiverStitcher;
use Jbryn\DistanceCalculator\River\RiverSummary;
use Jbryn\DistanceCalculator\River\RiverUnavailableException;

require __DIR__ . '/../vendor/autoload.php';

Dotenv::createImmutable(__DIR__ . '/..')->safeLoad();

$settings = Settings::fromEnvironment($_ENV + $_SERVER + getenv());
$catalogue = RiverCatalogue::fromFile($settings->riverCatalogueFile);

$rivers = $catalogue->search('');
$requested = array_slice($argv, 1);

if ($requested !== []) {
    $rivers = [];

    foreach ($requested as $argument) {
        $river = ctype_digit($argument) ? $catalogue->find((int) $argument) : null;

        if ($river === null) {
            fwrite(STDERR, "{$argument} is not the id of a river in {$settings->riverCatalogueFile}\n");

            exit(2);
        }

        $rivers[] = $river;
    }
}

$httpFactory = new HttpFactory();
$cache = new QueryCache($settings->overpassCacheDir);

// The query for features along a long river can run for several minutes
$fetcher = new RiverDataFetcher(
    new HttpOverpassClient(
        new Client(['timeout' => 960, 'connect_timeout' => 10]),
        $httpFactory,
        $httpFactory,
        $settings->overpassEndpoints,
        $settings->overpassUserAgent,
    ),
    $cache,
);

$saved = new RiverRepository(
    $catalogue,
    new CachedOverpassClient(new OfflineOverpassClient(), $cache, PHP_INT_MAX),
    new RiverStitcher(),
    new FeatureLocator(),
);

$failed = 0;

foreach ($rivers as $summary) {
    /** @var RiverSummary $summary */
    echo str_pad("{$summary->name} ({$summary->id})", 32);

    try {
        $fetcher->fetch($summary->id);
        $river = $saved->get($summary->id);

        printf("%8.1f km, %d features\n", $river->line->length() / 1000, count($river->features));
    } catch (OverpassUnavailableException $e) {
        $failed++;
        echo "not fetched, saved data unchanged: {$e->getMessage()}\n";
    } catch (RiverUnavailableException $e) {
        $failed++;
        echo "fetched but unusable: {$e->getMessage()}\n";
    }
}

exit($failed === 0 ? 0 : 1);
