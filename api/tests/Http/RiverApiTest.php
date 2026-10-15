<?php

declare(strict_types=1);

namespace Jbryn\Downriver\Tests\Http;

use Jbryn\Downriver\Config\Settings;
use Jbryn\Downriver\Overpass\OverpassClient;
use Jbryn\Downriver\Overpass\QueryCache;
use Jbryn\Downriver\River\RiverCatalogue;
use Jbryn\Downriver\River\RiverRelation;
use Jbryn\Downriver\River\RiverSummary;
use Jbryn\Downriver\Tests\Overpass\TemporaryDirectory;
use Jbryn\Downriver\Tests\River\RiverFixtures;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;

/**
 * The river endpoints, through the whole application and with the rivers it ships with.
 * Nothing here uses the network: the default settings answer from saved map data only.
 */
class RiverApiTest extends TestCase
{
    use SendsRequests;
    use TemporaryDirectory;

    private const GWDA = '/api/rivers/8974372';

    /** The IMGW gauging station at Ptusza, 57.55 km above the mouth by official kilometrage */
    private const PTUSZA = '53.3667,16.7964';

    /** Where the Gwda joins the Noteć */
    private const MOUTH = '53.0551,16.726';

    public function testSearchFindsRiversByName(): void
    {
        $response = $this->request('GET', '/api/rivers?q=wis');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('application/json', $response->getHeaderLine('Content-Type'));
        $this->assertSame(
            [
                'status' => 'success',
                'data' => [
                    ['id' => 34392, 'name' => 'Wisła'],
                    ['id' => 2020696, 'name' => 'Wisłok'],
                    ['id' => 7577712, 'name' => 'Wisłoka'],
                ],
            ],
            $this->json($response),
        );
    }

    public function testSearchIgnoresPolishLetters(): void
    {
        $this->assertSame(
            [['id' => 7581082, 'name' => 'Pasłęka']],
            $this->data($this->request('GET', '/api/rivers?q=PASLEKA')),
        );
    }

    public function testSearchWithoutTextListsEveryRiver(): void
    {
        $rivers = $this->data($this->request('GET', '/api/rivers'));

        $this->assertCount(15, $rivers);
        $this->assertSame(['id' => 19995496, 'name' => 'Biała (Tarnowska)'], $rivers[0]);
    }

    public function testSearchWithNoMatchReturnsAnEmptyList(): void
    {
        $response = $this->request('GET', '/api/rivers?q=amazon');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(['status' => 'success', 'data' => []], $this->json($response));
    }

    public function testSearchRejectsTextGivenAsAList(): void
    {
        $response = $this->request('GET', '/api/rivers?q[]=gwda');

        $this->assertSame(400, $response->getStatusCode());
        $this->assertSame(
            ['status' => 'error', 'message' => "Query parameter 'q' must be text"],
            $this->json($response),
        );
    }

    public function testRiverReturnsItsLineAndLength(): void
    {
        $response = $this->request('GET', self::GWDA);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('application/json', $response->getHeaderLine('Content-Type'));

        $river = $this->data($response);

        $this->assertSame(['id', 'name', 'length', 'points', 'features'], array_keys($river));
        $this->assertSame(8974372, $river['id']);
        $this->assertSame('Gwda', $river['name']);

        // Regression check: the Gwda measures about 142.9 km
        $this->assertSame(142928.6, $river['length']);

        $points = $river['points'];
        self::assertIsArray($points);
        $this->assertCount(2749, $points);
        $this->assertSame([53.85058, 16.669742], $points[0]);
        $this->assertSame([53.0548523, 16.7259211], $points[2748]);
    }

    public function testRiverListsItsObstaclesAndAccessPointsFromSourceToMouth(): void
    {
        $features = $this->features($this->data($this->request('GET', self::GWDA)));

        $this->assertCount(52, $features);
        $kinds = [];
        foreach ($features as $feature) {
            self::assertIsString($feature['kind']);
            $kinds[$feature['kind']] = ($kinds[$feature['kind']] ?? 0) + 1;
        }
        $this->assertSame(['bridge' => 33, 'weir' => 8, 'dam' => 8, 'put_in' => 3], $kinds);

        // A footbridge 24 m below the source
        $this->assertSame(
            [
                'kind' => 'bridge',
                'category' => 'access',
                'name' => null,
                'point' => [53.8505192, 16.6694025],
                'distanceFromSource' => 23.8,
            ],
            $features[0],
        );

        $this->assertSame('Most Bolesława Chrobrego', $features[42]['name']);
        $this->assertSame('obstacle', $features[5]['category']);
        $this->assertSame('weir', $features[5]['kind']);

        $distances = array_column($features, 'distanceFromSource');
        $sorted = $distances;
        sort($sorted);
        $this->assertSame($sorted, $distances);
    }

    public function testSectionListsWhatLiesOnItWithDistancesFromItsStart(): void
    {
        $section = $this->data($this->request('GET', self::GWDA . '/section?from=' . self::PTUSZA . '&to=' . self::MOUTH));
        $features = $this->features($section);

        $this->assertCount(27, $features);

        // The first thing below Ptusza is a bridge after 278 m, then the dam and weir of a power station
        $this->assertSame(
            ['kind' => 'bridge', 'category' => 'access', 'name' => null, 'point' => [53.3646679, 16.7940129], 'distance' => 278.3],
            $features[0],
        );
        $this->assertSame(['dam', 3548.2], [$features[1]['kind'], $features[1]['distance']]);
        $this->assertSame(['weir', 3548.8], [$features[2]['kind'], $features[2]['distance']]);

        $distances = array_column($features, 'distance');
        $sorted = $distances;
        sort($sorted);
        $this->assertSame($sorted, $distances);
        $this->assertLessThanOrEqual($section['length'], end($distances));
    }

    public function testSectionAgainstTheCurrentListsTheSameFeaturesInReverse(): void
    {
        $down = $this->data($this->request('GET', self::GWDA . '/section?from=' . self::PTUSZA . '&to=' . self::MOUTH));
        $up = $this->data($this->request('GET', self::GWDA . '/section?from=' . self::MOUTH . '&to=' . self::PTUSZA));

        $downFeatures = $this->features($down);
        $upFeatures = $this->features($up);

        $this->assertSame(
            array_reverse(array_column($downFeatures, 'point')),
            array_column($upFeatures, 'point'),
        );

        // The bridge 278 m below Ptusza is the last thing met when paddling up from the mouth
        $last = $upFeatures[count($upFeatures) - 1];
        self::assertIsFloat($down['length']);
        self::assertIsFloat($last['distance']);
        $this->assertEqualsWithDelta($down['length'] - 278.3, $last['distance'], 0.1);
    }

    public function testASectionWithNothingOnItHasAnEmptyFeatureList(): void
    {
        $section = $this->data($this->request('GET', self::GWDA . '/section?from=' . self::PTUSZA . '&to=' . self::PTUSZA));

        $this->assertSame([], $section['features']);
    }

    public function testARiverWithoutSavedFeatureDataIsUnavailable(): void
    {
        // A data folder that holds the course of the Gwda but nothing about what lies along it
        $this->createTemporaryDirectory();
        mkdir($this->directory);
        $course = (new QueryCache(RiverFixtures::SHIPPED))->path(RiverRelation::overpassQuery(8974372));
        copy($course, $this->directory . '/' . basename($course));

        try {
            $response = $this->request('GET', self::GWDA, settings: new Settings(
                logErrors: false,
                overpassCacheDir: $this->directory,
            ));

            $this->assertSame(503, $response->getStatusCode());
            $this->assertSame(
                ['status' => 'error', 'message' => 'The data for this river is unavailable'],
                $this->json($response),
            );
        } finally {
            $this->removeTemporaryDirectory();
        }
    }

    public function testUnknownRiverReturnsNotFound(): void
    {
        $response = $this->request('GET', '/api/rivers/1');

        $this->assertSame(404, $response->getStatusCode());
        $this->assertSame(['status' => 'error', 'message' => 'River not found'], $this->json($response));
    }

    public function testARiverLeftOutOfTheCatalogueIsNotFound(): void
    {
        // The Soła exists in OpenStreetMap, but is not offered because its map data has gaps
        $this->assertSame(404, $this->request('GET', '/api/rivers/545358')->getStatusCode());
    }

    #[DataProvider('idsThatAreNotNumbers')]
    public function testAnIdThatIsNotANumberMatchesNoRoute(string $id): void
    {
        $response = $this->request('GET', "/api/rivers/{$id}");

        $this->assertSame(404, $response->getStatusCode());
        $this->assertSame(['status' => 'error', 'message' => 'Not found.'], $this->json($response));
    }

    /**
     * @return array<array{string}>
     */
    public static function idsThatAreNotNumbers(): array
    {
        return [['gwda'], ['-5'], ['12.5'], ['1234567890123456']];
    }

    public function testSectionMeasuresAlongTheRiver(): void
    {
        $response = $this->request('GET', self::GWDA . '/section?from=' . self::PTUSZA . '&to=' . self::MOUTH);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('application/json', $response->getHeaderLine('Content-Type'));

        $section = $this->data($response);

        $this->assertSame(['river', 'length', 'downstream', 'from', 'to', 'points', 'features'], array_keys($section));
        $this->assertSame(['id' => 8974372, 'name' => 'Gwda'], $section['river']);

        // 57.81 km along the bends; IMGW gives 57.55 km and the straight line is 34.97 km
        $this->assertSame(57810.7, $section['length']);
        $this->assertTrue($section['downstream']);

        $this->assertSame(
            ['point' => [53.3665124, 16.7965265], 'distanceFromSource' => 85111.3, 'offset' => 22.5],
            $section['from'],
        );
        $this->assertSame(
            ['point' => [53.0548549, 16.7260184], 'distanceFromSource' => 142922.1, 'offset' => 27.3],
            $section['to'],
        );

        $points = $section['points'];
        self::assertIsArray($points);
        $this->assertCount(1359, $points);
        $this->assertSame([53.3665124, 16.7965265], $points[0]);
        $this->assertSame([53.0548549, 16.7260184], $points[1358]);
    }

    public function testSectionAgainstTheCurrentIsTheSameLengthAndRunsBackwards(): void
    {
        $down = $this->data($this->request('GET', self::GWDA . '/section?from=' . self::PTUSZA . '&to=' . self::MOUTH));
        $up = $this->data($this->request('GET', self::GWDA . '/section?from=' . self::MOUTH . '&to=' . self::PTUSZA));

        $this->assertFalse($up['downstream']);
        $this->assertSame($down['length'], $up['length']);
        $this->assertSame($down['from'], $up['to']);
        $this->assertSame($down['to'], $up['from']);

        self::assertIsArray($down['points']);
        $this->assertSame(array_reverse($down['points']), $up['points']);
    }

    public function testSectionBetweenTheSamePointTwiceHasNoLength(): void
    {
        $section = $this->data($this->request('GET', self::GWDA . '/section?from=' . self::PTUSZA . '&to=' . self::PTUSZA));

        $this->assertEquals(0, $section['length']);
        $this->assertTrue($section['downstream']);
        $this->assertSame([[53.3665124, 16.7965265], [53.3665124, 16.7965265]], $section['points']);
    }

    public function testSectionAcceptsSpacesAroundTheNumbers(): void
    {
        $from = rawurlencode(' 53.3667 , 16.7964 ');

        $section = $this->data($this->request('GET', self::GWDA . "/section?from={$from}&to=" . self::MOUTH));

        $this->assertSame(57810.7, $section['length']);
    }

    public function testSectionAcceptsWholeNumbers(): void
    {
        // A valid point, so not a 400; it is just nowhere near the river
        $response = $this->request('GET', self::GWDA . '/section?from=53,17&to=' . self::MOUTH);

        $this->assertSame(422, $response->getStatusCode());
    }

    #[DataProvider('invalidSectionQueries')]
    public function testSectionRejectsInvalidPoints(string $query, string $message): void
    {
        $response = $this->request('GET', self::GWDA . '/section?' . $query);

        $this->assertSame(400, $response->getStatusCode());
        $this->assertSame(['status' => 'error', 'message' => $message], $this->json($response));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function invalidSectionQueries(): array
    {
        $required = "Query parameter '%s' is required, as latitude,longitude";
        $format = "Query parameter '%s' must be latitude,longitude, for example 53.54,16.95";

        return [
            'nothing given' => ['', sprintf($required, 'from')],
            'no start' => ['to=' . self::MOUTH, sprintf($required, 'from')],
            'no end' => ['from=' . self::PTUSZA, sprintf($required, 'to')],
            'empty end' => ['from=' . self::PTUSZA . '&to=', sprintf($required, 'to')],
            'start given twice as a list' => ['from[]=53.3&from[]=16.7&to=' . self::MOUTH, sprintf($required, 'from')],
            'words' => ['from=ptusza&to=' . self::MOUTH, sprintf($format, 'from')],
            'one number' => ['from=53.3667&to=' . self::MOUTH, sprintf($format, 'from')],
            'three numbers' => ['from=' . self::PTUSZA . '&to=53.05,16.72,5', sprintf($format, 'to')],
            'semicolon' => ['from=53.3667;16.7964&to=' . self::MOUTH, sprintf($format, 'from')],
            'scientific notation' => ['from=5e1,16.7&to=' . self::MOUTH, sprintf($format, 'from')],
            'not a number' => ['from=NAN,16.7&to=' . self::MOUTH, sprintf($format, 'from')],
            'latitude off the globe' => [
                'from=95,16.7&to=' . self::MOUTH,
                "Query parameter 'from': Latitude must be between -90 and 90 degrees, got 95",
            ],
            'longitude off the globe' => [
                'from=' . self::PTUSZA . '&to=53.05,-181',
                "Query parameter 'to': Longitude must be between -180 and 180 degrees, got -181",
            ],
        ];
    }

    public function testSectionRejectsAPointFarFromTheRiver(): void
    {
        // Warsaw
        $response = $this->request('GET', self::GWDA . '/section?from=' . self::PTUSZA . '&to=52.2297,21.0122');

        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame('application/json', $response->getHeaderLine('Content-Type'));
        $this->assertSame(
            [
                'status' => 'error',
                'message' => "The 'to' point is 301.0 km from the river Gwda; it must be within 2 km",
            ],
            $this->json($response),
        );
    }

    public function testSectionOfAnUnknownRiverReturnsNotFound(): void
    {
        $response = $this->request('GET', '/api/rivers/1/section?from=' . self::PTUSZA . '&to=' . self::MOUTH);

        $this->assertSame(404, $response->getStatusCode());
        $this->assertSame(['status' => 'error', 'message' => 'River not found'], $this->json($response));
    }

    public function testARiverWithoutSavedDataIsUnavailable(): void
    {
        $response = $this->request('GET', '/api/rivers/999', definitions: [
            RiverCatalogue::class => new RiverCatalogue([new RiverSummary(999, 'Nowhere')]),
        ]);

        $this->assertSame(503, $response->getStatusCode());
        $this->assertSame('application/json', $response->getHeaderLine('Content-Type'));
        $this->assertSame(
            ['status' => 'error', 'message' => 'The data for this river is unavailable'],
            $this->json($response),
        );
    }

    public function testARiverWithGapsIsUnavailableForSectionsToo(): void
    {
        $response = $this->request('GET', '/api/rivers/545358/section?from=49.6,19.1&to=49.7,19.2', definitions: [
            RiverCatalogue::class => new RiverCatalogue([new RiverSummary(545358, 'Soła')]),
            OverpassClient::class => RiverFixtures::client(),
        ]);

        $this->assertSame(503, $response->getStatusCode());
    }

    public function testUnavailableDataIsLoggedWithItsCauseButClientMistakesAreNot(): void
    {
        $this->createTemporaryDirectory();
        mkdir($this->directory);
        $log = $this->directory . '/error.log';
        $previous = ini_set('error_log', $log);

        try {
            $this->request('GET', '/api/rivers/1', settings: new Settings(logErrors: true));
            $this->assertFileDoesNotExist($log);

            $this->request('GET', '/api/rivers/999', settings: new Settings(logErrors: true), definitions: [
                RiverCatalogue::class => new RiverCatalogue([new RiverSummary(999, 'Nowhere')]),
            ]);
            $this->assertStringContainsString('No map data for river 999', (string) file_get_contents($log));
        } finally {
            ini_set('error_log', (string) $previous);
            $this->removeTemporaryDirectory();
        }
    }

    #[DataProvider('everyKindOfResponse')]
    public function testEveryResponseCarriesCorsHeaders(string $path): void
    {
        $response = $this->request('GET', $path);

        $this->assertSame('http://localhost:5173', $response->getHeaderLine('Access-Control-Allow-Origin'));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function everyKindOfResponse(): array
    {
        return [
            'search' => ['/api/rivers?q=gwda'],
            'river' => [self::GWDA],
            'section' => [self::GWDA . '/section?from=' . self::PTUSZA . '&to=' . self::MOUTH],
            'not found' => ['/api/rivers/1'],
            'invalid point' => [self::GWDA . '/section'],
            'point too far' => [self::GWDA . '/section?from=0,0&to=0,0'],
        ];
    }

    public function testRiversCannotBeChanged(): void
    {
        $response = $this->request('POST', self::GWDA);

        $this->assertSame(405, $response->getStatusCode());
        $this->assertSame('GET', $response->getHeaderLine('Allow'));
    }

    /**
     * @param array<mixed> $data
     *
     * @return list<array<string, mixed>>
     */
    private function features(array $data): array
    {
        $features = $data['features'] ?? null;
        self::assertIsArray($features);

        $checked = [];
        foreach ($features as $feature) {
            self::assertIsArray($feature);

            $fields = [];
            foreach ($feature as $key => $value) {
                self::assertIsString($key);
                $fields[$key] = $value;
            }

            self::assertSame(['kind', 'category', 'name', 'point'], array_slice(array_keys($fields), 0, 4));
            self::assertCount(5, $fields);
            $checked[] = $fields;
        }

        return $checked;
    }

    /**
     * @return array<mixed>
     */
    private function data(ResponseInterface $response): array
    {
        $data = $this->json($response)['data'] ?? null;
        self::assertIsArray($data);

        return $data;
    }
}
