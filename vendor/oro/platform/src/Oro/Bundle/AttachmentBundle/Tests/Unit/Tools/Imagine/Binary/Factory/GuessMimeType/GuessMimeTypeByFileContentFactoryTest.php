<?php

namespace Oro\Bundle\AttachmentBundle\Tests\Unit\Tools\Imagine\Binary\Factory\GuessMimeType;

use Liip\ImagineBundle\Binary\MimeTypeGuesserInterface;
use Liip\ImagineBundle\Model\Binary;
use Oro\Bundle\AttachmentBundle\Tools\Imagine\Binary\Factory\GuessMimeType\GuessMimeTypeByFileContentFactory;
use Symfony\Component\HttpFoundation\File\MimeType\ExtensionGuesserInterface;

class GuessMimeTypeByFileContentFactoryTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var GuessMimeTypeByFileContentFactory
     */
    private $factory;

    /**
     * @var MimeTypeGuesserInterface|\PHPUnit\Framework\MockObject\MockObject
     */
    private $mimeTypeGuesser;

    /**
     * @var ExtensionGuesserInterface|\PHPUnit\Framework\MockObject\MockObject
     */
    private $extensionGuesser;

    /**
     * {@inheritDoc}
     */
    protected function setUp(): void
    {
        $this->mimeTypeGuesser = $this->createMock(MimeTypeGuesserInterface::class);
        $this->extensionGuesser = $this->createMock(ExtensionGuesserInterface::class);

        $this->factory = new GuessMimeTypeByFileContentFactory($this->mimeTypeGuesser, $this->extensionGuesser);
    }

    public function testCreateImagineBinary()
    {
        $content = 'binary_content';
        $mimeType = 'image/png';
        $format = 'png';

        $this->mimeTypeGuesser
            ->method('guess')
            ->with($content)
            ->willReturn($mimeType);

        $this->extensionGuesser
            ->method('guess')
            ->with($mimeType)
            ->willReturn($format);

        static::assertEquals(new Binary($content, $mimeType, $format), $this->factory->createImagineBinary($content));
    }
}
