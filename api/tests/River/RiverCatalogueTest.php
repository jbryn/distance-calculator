<?php

declare(strict_types=1);

namespace Jbryn\Downriver\Tests\River;

use Jbryn\Downriver\Config\Settings;
use Jbryn\Downriver\River\RiverCatalogue;
use Jbryn\Downriver\River\RiverSummary;
use Jbryn\Downriver\Tests\Overpass\TemporaryDirectory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class RiverCatalogueTest extends TestCase
{
    use TemporaryDirectory;

    protected function setUp(): void
    {
        $this->createTemporaryDirectory();
        mkdir($this->directory);
    }

    protected function tearDown(): void
    {
        $this->removeTemporaryDirectory();
    }

    public function testFindsARiverById(): void
    {
        $river = self::catalogue()->find(6124894);

        $this->assertNotNull($river);
        $this->assertSame(6124894, $river->id);
        $this->assertSame('Noteć', $river->name);
    }

    public function testFindReturnsNothingForAnUnknownId(): void
    {
        $this->assertNull(self::catalogue()->find(1));
    }

    /**
     * @param list<string> $expected
     */
    #[DataProvider('searches')]
    public function testSearch(string $text, array $expected): void
    {
        $this->assertSame($expected, self::names(self::catalogue()->search($text)));
    }

    /**
     * @return array<string, array{string, list<string>}>
     */
    public static function searches(): array
    {
        return [
            'exact name' => ['Gwda', ['Gwda']],
            'ignores case' => ['gWDA', ['Gwda']],
            'ignores surrounding spaces' => ['  gwda ', ['Gwda']],
            'beginning of a name, in alphabetical order' => ['wis', ['Wisła', 'Wisłok', 'Wisłoka']],
            'without Polish letters' => ['notec', ['Noteć']],
            'with Polish letters' => ['NOTEĆ', ['Noteć']],
            'middle of a name' => ['ote', ['Noteć']],
            'names starting with the text come first' => ['w', ['Warta', 'Wisła', 'Wisłok', 'Wisłoka', 'Gwda']],
            'nothing matches' => ['amazon', []],
            'empty text lists everything, alphabetically ignoring accents' => [
                '',
                ['Gwda', 'Noteć', 'Odra', 'Warta', 'Wisła', 'Wisłok', 'Wisłoka'],
            ],
            'only spaces counts as empty' => ['   ', ['Gwda', 'Noteć', 'Odra', 'Warta', 'Wisła', 'Wisłok', 'Wisłoka']],
        ];
    }

    public function testReadsRiversFromAFile(): void
    {
        $path = $this->file('[{"id": 8974372, "name": "Gwda"}, {"id": 34392, "name": "Wisła"}]');

        $catalogue = RiverCatalogue::fromFile($path);

        $this->assertSame(['Gwda', 'Wisła'], self::names($catalogue->search('')));
        $this->assertSame('Wisła', $catalogue->find(34392)?->name);
    }

    #[DataProvider('unusableFiles')]
    public function testRejectsAnUnusableFile(string $contents, string $message): void
    {
        $path = $this->file($contents);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage($message);

        RiverCatalogue::fromFile($path);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function unusableFiles(): array
    {
        return [
            'not JSON' => ['[{"id": 1,', 'is not valid JSON'],
            'not a list' => ['"rivers"', 'must be a list of rivers'],
            'entry that is not an object' => ['[5]', 'needs a numeric "id" and a "name"'],
            'id as text' => ['[{"id": "8974372", "name": "Gwda"}]', 'needs a numeric "id" and a "name"'],
            'missing name' => ['[{"id": 8974372}]', 'needs a numeric "id" and a "name"'],
            'blank name' => ['[{"id": 8974372, "name": " "}]', 'needs a numeric "id" and a "name"'],
        ];
    }

    public function testRejectsAMissingFile(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Could not read the river catalogue');

        RiverCatalogue::fromFile($this->directory . '/nope.json');
    }

    public function testTheShippedCatalogueLoads(): void
    {
        $catalogue = RiverCatalogue::fromFile(Settings::DEFAULT_RIVER_CATALOGUE_FILE);

        $this->assertCount(15, $catalogue->search(''));
        $this->assertSame('Gwda', $catalogue->find(8974372)?->name);
    }

    private static function catalogue(): RiverCatalogue
    {
        return new RiverCatalogue([
            new RiverSummary(34392, 'Wisła'),
            new RiverSummary(7577712, 'Wisłoka'),
            new RiverSummary(8974372, 'Gwda'),
            new RiverSummary(2020696, 'Wisłok'),
            new RiverSummary(6124894, 'Noteć'),
            new RiverSummary(3272339, 'Warta'),
            new RiverSummary(387605, 'Odra'),
        ]);
    }

    /**
     * @param list<RiverSummary> $rivers
     *
     * @return list<string>
     */
    private static function names(array $rivers): array
    {
        return array_map(static fn(RiverSummary $river): string => $river->name, $rivers);
    }

    private function file(string $contents): string
    {
        $path = $this->directory . '/rivers.json';
        file_put_contents($path, $contents);

        return $path;
    }
}
