<?php

declare(strict_types=1);

namespace Tests\Unit\Import;

use App\Services\Import\ArchiveException;
use App\Services\Import\ArchiveExtractor;
use PHPUnit\Framework\TestCase;
use ZipArchive;

final class ArchiveExtractorTest extends TestCase
{
    private string $tmp;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tmp = sys_get_temp_dir().'/ax_'.uniqid();
        mkdir($this->tmp, 0777, true);
    }

    protected function tearDown(): void
    {
        exec('rm -rf '.escapeshellarg($this->tmp));
        parent::tearDown();
    }

    private function makeZip(string $name, array $entries): string
    {
        $path = $this->tmp.'/'.$name;
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE);
        foreach ($entries as $entryName => $contents) {
            $zip->addFromString($entryName, $contents);
        }
        $zip->close();

        return $path;
    }

    public function test_it_extracts_entries_to_the_destination(): void
    {
        $zip = $this->makeZip('ok.zip', ['a.txt' => 'hello', 'sub/b.txt' => 'world']);
        $dest = $this->tmp.'/out';

        (new ArchiveExtractor)->extract($zip, $dest);

        $this->assertSame('hello', file_get_contents($dest.'/a.txt'));
        $this->assertSame('world', file_get_contents($dest.'/sub/b.txt'));
    }

    public function test_it_rejects_an_entry_that_escapes_the_destination(): void
    {
        $zip = $this->makeZip('evil.zip', ['../escaped.txt' => 'pwned']);

        $this->expectException(ArchiveException::class);
        $this->expectExceptionMessageMatches('/outside/i');

        (new ArchiveExtractor)->extract($zip, $this->tmp.'/out');
    }

    public function test_it_rejects_an_absolute_entry_path(): void
    {
        $zip = $this->makeZip('abs.zip', ['/etc/passwd' => 'pwned']);

        $this->expectException(ArchiveException::class);
        $this->expectExceptionMessageMatches('/absolute/i');

        (new ArchiveExtractor)->extract($zip, $this->tmp.'/out');
    }

    public function test_it_rejects_a_symlink_entry(): void
    {
        $path = $this->tmp.'/symlink.zip';
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE);
        $zip->addFromString('link', '../../outside');
        $zip->setExternalAttributesName('link', ZipArchive::OPSYS_UNIX, 0xA1FF << 16);
        $zip->close();

        $this->expectException(ArchiveException::class);
        $this->expectExceptionMessageMatches('/type/i');

        (new ArchiveExtractor)->extract($path, $this->tmp.'/out');
    }

    public function test_it_rejects_an_archive_with_too_many_entries(): void
    {
        $zip = $this->makeZip('many.zip', ['a.txt' => 'a', 'b.txt' => 'b', 'c.txt' => 'c']);

        $this->expectException(ArchiveException::class);
        $this->expectExceptionMessageMatches('/entries/i');

        (new ArchiveExtractor(maxEntries: 2))->extract($zip, $this->tmp.'/out');
    }

    public function test_it_rejects_an_archive_whose_contents_exceed_the_size_cap(): void
    {
        $zip = $this->makeZip('big.zip', ['a.txt' => str_repeat('x', 5000)]);

        $this->expectException(ArchiveException::class);
        $this->expectExceptionMessageMatches('/size/i');

        (new ArchiveExtractor(maxTotalBytes: 1000))->extract($zip, $this->tmp.'/out');
    }

    public function test_it_rejects_a_file_that_is_not_a_zip(): void
    {
        $path = $this->tmp.'/not.zip';
        file_put_contents($path, 'definitely not a zip');

        $this->expectException(ArchiveException::class);

        (new ArchiveExtractor)->extract($path, $this->tmp.'/out');
    }
}
