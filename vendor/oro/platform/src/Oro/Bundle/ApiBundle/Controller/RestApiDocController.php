<?php

namespace Oro\Bundle\ApiBundle\Controller;

use Nelmio\ApiDocBundle\Annotation\ApiDoc;
use Nelmio\ApiDocBundle\Extractor\ApiDocExtractor;
use Nelmio\ApiDocBundle\Formatter\FormatterInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

/**
 * REST API Sandbox controller.
 */
class RestApiDocController
{
    /** @var ApiDocExtractor */
    private $extractor;

    /** @var FormatterInterface */
    private $formatter;

    /** @var SessionInterface */
    private $session;

    public function __construct(ApiDocExtractor $extractor, FormatterInterface $formatter, SessionInterface $session)
    {
        $this->extractor = $extractor;
        $this->formatter = $formatter;
        $this->session = $session;
    }

    public function indexAction(string $view = ApiDoc::DEFAULT_VIEW): Response
    {
        $response = $this->getHtmlResponse(
            $this->formatter->format($this->extractor->all($view))
        );
        $this->ensureSessionExists();

        return $response;
    }

    public function resourceAction(string $view, string $resource): Response
    {
        $apiResource = $this->getApiResource($view, $resource);
        if (null === $apiResource) {
            return $this->getResourceNotFoundResponse();
        }

        $response = $this->getHtmlResponse(
            $this->formatter->formatOne($apiResource)
        );
        $this->ensureSessionExists();

        return $response;
    }

    private function getApiResource(string $view, string $resource): ?ApiDoc
    {
        $apiResource = null;
        $apiDoc = $this->extractor->all($view);
        foreach ($apiDoc as $item) {
            $annotation = $item['annotation'];
            if ($this->getResourceId($annotation) === $resource) {
                $apiResource = $annotation;
                break;
            }
        }

        return $apiResource;
    }

    private function getResourceId(ApiDoc $annotation): string
    {
        return
            strtolower($annotation->getMethod())
            . '-'
            . str_replace('/', '-', $annotation->getRoute()->getPath());
    }

    private function getHtmlResponse(string $content): Response
    {
        return new Response(
            $content,
            Response::HTTP_OK,
            ['Content-Type' => 'text/html']
        );
    }

    private function getResourceNotFoundResponse(): Response
    {
        return new Response('', Response::HTTP_NOT_FOUND);
    }

    private function ensureSessionExists(): void
    {
        /**
         * To correct work of "Session" authentication type on API Sandbox, the session must exist
         * and a cookie with the session identifier must be sent to a browser.
         * To achieve this we just add some value to the session if the session does not contain it yet.
         */
        if (!$this->session->get('api_sandbox')) {
            $this->session->set('api_sandbox', true);
        }
    }
}
