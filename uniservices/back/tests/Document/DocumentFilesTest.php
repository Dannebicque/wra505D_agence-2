<?php

declare(strict_types=1);

namespace App\Tests\Document;

use DocumentBundle\Entity\Document;
use DocumentBundle\Services\DocumentFiles;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

final class DocumentFilesTest extends TestCase
{
    private string $storageDir;

    protected function setUp(): void
    {
        $this->storageDir = sys_get_temp_dir().'/document-files-'.bin2hex(random_bytes(4));
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->storageDir);
    }

    public function testFindsStoredFile(): void
    {
        $files = new DocumentFiles($this->storageDir);
        $files->store(__FILE__, 'support.pdf');

        self::assertSame($this->storageDir.'/support.pdf', $files->path((new Document())->setFilename('support.pdf')));
    }

    public function testHasNoPathForDocumentWithoutStoredFile(): void
    {
        self::assertNull((new DocumentFiles($this->storageDir))->path((new Document())->setFilename('absent.pdf')));
    }

    public function testKeepsPathSegmentsOutOfStorage(): void
    {
        $files = new DocumentFiles($this->storageDir);
        $files->store(__FILE__, 'secret.pdf');

        self::assertSame($this->storageDir.'/secret.pdf', $files->path((new Document())->setFilename('../../secret.pdf')));
    }

    public function testServesInlineOnlyPreviewableTypes(): void
    {
        $files = new DocumentFiles($this->storageDir);

        self::assertTrue($files->isInline('application/pdf'));
        self::assertTrue($files->isInline('image/png'));
        self::assertFalse($files->isInline('text/html'));
        self::assertFalse($files->isInline('image/svg+xml'));
    }
}
