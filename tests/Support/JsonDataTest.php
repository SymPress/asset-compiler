<?php

declare(strict_types=1);

namespace SymPress\AssetCompiler\Tests\Support;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use SymPress\AssetCompiler\Support\JsonData;

final class JsonDataTest extends TestCase
{
    private ?string $jsonFile = null;

    protected function tearDown(): void
    {
        if ($this->jsonFile !== null && is_file($this->jsonFile)) {
            unlink($this->jsonFile);
        }
    }

    public function testDecodeReportsContextAndJsonError(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Could not deserialize JSON for asset compiler config file "/tmp/config.json"');
        $this->expectExceptionMessage('Syntax error');

        JsonData::decode('{"script":', 'asset compiler config file "/tmp/config.json"');
    }

    public function testDecodeFileAllowsEmptyJsonFilesWhenRequested(): void
    {
        $file = $this->tempJsonFile();
        file_put_contents($file, " \n ");

        self::assertSame([], JsonData::decodeFile($file, 'empty asset compiler config', allowEmpty: true));
    }

    public function testObjectRejectsJsonLists(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Expected JSON object for package manifest "/tmp/package.json".');

        JsonData::object(['build'], 'package manifest "/tmp/package.json"');
    }

    public function testDecodeObjectAcceptsEmptyJsonObjects(): void
    {
        self::assertSame([], JsonData::decodeObject('{}', 'package manifest "/tmp/package.json"'));
    }

    public function testDecodeObjectRejectsJsonLists(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Expected JSON object for package manifest "/tmp/package.json".');

        JsonData::decodeObject('[]', 'package manifest "/tmp/package.json"');
    }

    public function testStringKeyedArrayDropsNumericKeys(): void
    {
        self::assertSame(
            ['script' => 'build'],
            JsonData::stringKeyedArray(['script' => 'build', 1 => 'ignored']),
        );
    }

    public function testEncodeReportsContextAndJsonError(): void
    {
        $value = [];
        $value['self'] = &$value;

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Could not serialize JSON for asset hash context');
        $this->expectExceptionMessage('Recursion detected');

        JsonData::encode($value, 'asset hash context');
    }

    private function tempJsonFile(): string
    {
        $file = tempnam(sys_get_temp_dir(), 'sympress_asset_json_test_');
        self::assertIsString($file);
        $this->jsonFile = $file;

        return $file;
    }
}
