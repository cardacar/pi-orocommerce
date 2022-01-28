<?php

namespace Oro\Bundle\AttachmentBundle\Tests\Behat\Context;

use Behat\Symfony2Extension\Context\KernelDictionary;
use GuzzleHttp\Client;
use Oro\Bundle\AttachmentBundle\Entity\File;
use Oro\Bundle\AttachmentBundle\Manager\AttachmentManager;
use Oro\Bundle\TestFrameworkBundle\Behat\Context\OroFeatureContext;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\PropertyAccess\PropertyAccess;

abstract class AttachmentContext extends OroFeatureContext
{
    use KernelDictionary;

    /**
     * Download the file using active session
     */
    public function downloadAttachment(string $url): ResponseInterface
    {
        $cookieJar = $this->getCookieJar($this->getSession());
        $client = new Client([
            'cookies' => $cookieJar,
            'http_errors' => false,
        ]);

        return $client->get($this->locatePath($url));
    }

    public function getAttachmentUrl($entity, string $attachmentField): string
    {
        $attachment = $this->getAttachmentByEntity($entity, $attachmentField);

        return $this->getAttachmentManager()->getFileUrl($attachment);
    }

    protected function getAttachmentByEntity($entity, string $field): File
    {
        $accessor = PropertyAccess::createPropertyAccessor();

        return $accessor->getValue($entity, $field);
    }

    protected function getAttachmentManager(): AttachmentManager
    {
        return $this->getContainer()->get('oro_attachment.manager');
    }

    abstract protected function assertResponseSuccess(ResponseInterface $response): void;

    abstract protected function assertResponseFail(ResponseInterface $response): void;
}
