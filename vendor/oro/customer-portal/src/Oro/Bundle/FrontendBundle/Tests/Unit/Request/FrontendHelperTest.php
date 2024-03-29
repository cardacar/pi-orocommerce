<?php

namespace Oro\Bundle\FrontendBundle\Tests\Unit\Request;

use Oro\Bundle\FrontendBundle\Request\FrontendHelper;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

class FrontendHelperTest extends \PHPUnit\Framework\TestCase
{
    private const BACKEND_PREFIX = '/admin';

    private function getRequestStack(Request $currentRequest = null): RequestStack
    {
        $requestStack = new RequestStack();
        if (null !== $currentRequest) {
            $requestStack->push($currentRequest);
        }

        return $requestStack;
    }

    /**
     * @dataProvider isFrontendRequestDataProvider
     */
    public function testIsFrontendRequest(string $path, bool $isFrontend)
    {
        $request = Request::create($path);

        $helper = new FrontendHelper(self::BACKEND_PREFIX, $this->getRequestStack($request));
        $this->assertSame($isFrontend, $helper->isFrontendRequest());
    }

    /**
     * @return array
     */
    public function isFrontendRequestDataProvider()
    {
        return [
            'backend'                             => [
                'path'       => self::BACKEND_PREFIX . '/backend',
                'isFrontend' => false
            ],
            'frontend'                            => [
                'path'       => '/frontend',
                'isFrontend' => true
            ],
            'frontend with backend part'          => [
                'path'       => '/frontend' . self::BACKEND_PREFIX,
                'isFrontend' => true
            ],
            'frontend with backend part and slug' => [
                'path'       => '/frontend' . self::BACKEND_PREFIX . '/slug',
                'isFrontend' => true
            ],
            'frontend that starts with backend part' => [
                'path'       => self::BACKEND_PREFIX . 'instration',
                'isFrontend' => true
            ],
            'backend that is the same as backend part' => [
                'path'       => self::BACKEND_PREFIX,
                'isFrontend' => false
            ]
        ];
    }

    public function testIsFrontendRequestWithoutPath()
    {
        $helper = new FrontendHelper(self::BACKEND_PREFIX, $this->getRequestStack());
        $this->assertFalse($helper->isFrontendRequest());
    }

    public function testIsFrontendUrlForBackendUrl()
    {
        $helper = new FrontendHelper(self::BACKEND_PREFIX, $this->getRequestStack());
        $this->assertFalse($helper->isFrontendUrl(self::BACKEND_PREFIX . '/test'));
    }

    public function testIsFrontendUrl()
    {
        $helper = new FrontendHelper(self::BACKEND_PREFIX, $this->getRequestStack());
        $this->assertTrue($helper->isFrontendUrl('/test'));
    }

    public function testEmulateFrontendRequest()
    {
        $request = Request::create(self::BACKEND_PREFIX . '/backend');

        $helper = new FrontendHelper(self::BACKEND_PREFIX, $this->getRequestStack($request));
        $helper->emulateFrontendRequest();
        $this->assertTrue($helper->isFrontendRequest());

        $helper->resetRequestEmulation();
        $this->assertFalse($helper->isFrontendRequest());
    }

    public function testEmulateBackendRequest()
    {
        $request = Request::create('/frontend');

        $helper = new FrontendHelper(self::BACKEND_PREFIX, $this->getRequestStack($request));
        $helper->emulateBackendRequest();
        $this->assertFalse($helper->isFrontendRequest());

        $helper->resetRequestEmulation();
        $this->assertTrue($helper->isFrontendRequest());
    }
}
