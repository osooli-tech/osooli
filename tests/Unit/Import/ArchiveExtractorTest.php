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

    /**
     * Builds a ZIP whose one entry decompresses to $realSize real bytes, but
     * whose declared uncompressed size — in both the local file header and
     * the central directory record, the only two places libzip's own
     * statIndex() ever reads it from — has been patched down to $declaredSize
     * after the fact. Deflate compression (ZipArchive's default) is what
     * makes this realistic: a large run of zero bytes compresses to a small
     * fraction of its real size, exactly the shape of an actual zip bomb, so
     * the compressed-size field (left untouched) differs from the
     * uncompressed-size field being patched and the two can be told apart
     * unambiguously by value alone.
     */
    private function makeZipBombWithLyingDeclaredSize(string $name, int $realSize, int $declaredSize): string
    {
        $path = $this->tmp.'/'.$name;
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE);
        $zip->addFromString('bomb.txt', str_repeat("\0", $realSize));
        $zip->close();

        $contents = (string) file_get_contents($path);
        $needle = pack('V', $realSize);
        $replacement = pack('V', $declaredSize);

        $occurrences = substr_count($contents, $needle);

        // Exactly two occurrences are expected: the local file header's
        // uncompressed-size field and the central directory's uncompressed-size
        // field for this one entry. Asserting this here (rather than silently
        // patching however many matches turn up) keeps the test honest about
        // what it is actually proving — that both copies of the declared size
        // were lies, not just one.
        $this->assertSame(2, $occurrences, 'expected exactly 2 occurrences of the declared size to patch');

        file_put_contents($path, str_replace($needle, $replacement, $contents));

        return $path;
    }

    public function test_it_rejects_an_archive_whose_declared_size_understates_the_real_bytes(): void
    {
        // 5 MB of real, decompressible content, declared as just 1 byte —
        // exactly the shape of the reviewer's proof-of-concept zip bomb.
        $zip = $this->makeZipBombWithLyingDeclaredSize('bomb1.zip', 5 * 1024 * 1024, 1);

        $this->expectException(ArchiveException::class);
        $this->expectExceptionMessageMatches('/size/i');

        (new ArchiveExtractor(maxTotalBytes: 1024))->extract($zip, $this->tmp.'/out');
    }

    public function test_a_rejected_zip_bomb_leaves_nothing_in_the_destination(): void
    {
        $zip = $this->makeZipBombWithLyingDeclaredSize('bomb2.zip', 5 * 1024 * 1024, 1);
        $dest = $this->tmp.'/out';

        try {
            (new ArchiveExtractor(maxTotalBytes: 1024))->extract($zip, $dest);
            $this->fail('Expected an ArchiveException for the oversized archive.');
        } catch (ArchiveException) {
            // expected — assertions happen below, once extraction has aborted.
        }

        $this->assertDirectoryDoesNotExist($dest, 'a rejected archive must not leave a partial extraction behind');
    }

    public function test_a_legitimate_archive_comfortably_under_the_cap_still_extracts(): void
    {
        // Guards against over-tightening: a real archive whose entries are
        // nowhere near the cap must still extract normally once the cap is
        // enforced against actual bytes instead of declared ones.
        $zip = $this->makeZip('fine.zip', ['a.txt' => str_repeat('y', 500), 'sub/b.txt' => 'hello']);
        $dest = $this->tmp.'/out';

        (new ArchiveExtractor(maxTotalBytes: 10_000))->extract($zip, $dest);

        $this->assertSame(str_repeat('y', 500), file_get_contents($dest.'/a.txt'));
        $this->assertSame('hello', file_get_contents($dest.'/sub/b.txt'));
    }
}
