<?php

namespace Akeneo\Bundle\BatchBundle\Tests\Unit\Notification;

use Akeneo\Bundle\BatchBundle\Notification\MailNotifier;

/**
 * Test related class
 *
 */
class MailNotifierTest extends \PHPUnit\Framework\TestCase
{
    protected $handler;
    protected $tokenStorage;
    protected $twig;
    protected $mailer;
    protected $notifier;

    protected function setUp()
    {
        $this->handler         = $this->getDisabledConstructorMock(
            'Akeneo\Bundle\BatchBundle\Monolog\Handler\BatchLogHandler'
        );
        $this->tokenStorage = $this->getDisabledConstructorMock(
            'Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface'
        );
        $this->twig            = $this->getDisabledConstructorMock('\Twig_Environment');
        $this->mailer          = $this->getDisabledConstructorMock('\Swift_Mailer');

        $this->notifier = new MailNotifier(
            $this->handler,
            $this->tokenStorage,
            $this->twig,
            $this->mailer,
            'no-reply@example.com'
        );
    }

    public function testIsANotifier()
    {
        $this->assertInstanceOf('Akeneo\Bundle\BatchBundle\Notification\Notifier', $this->notifier);
    }

    public function testNotifyWithLoggedInUserEmail()
    {
        $this->markTestSkipped();
        $user = $this->getUserMock();
        $token = $this->getTokenMock($user);
        $this->tokenStorage
            ->expects($this->once())
            ->method('getToken')
            ->will($this->returnValue($token));

        $this->handler
            ->expects($this->once())
            ->method('getFilename')
            ->will($this->returnValue('/tmp/foo.log'));

        $jobExecution = $this->getDisabledConstructorMock('Akeneo\Bundle\BatchBundle\Entity\JobExecution');
        $parameters = array(
            'jobExecution' => $jobExecution,
            'log'          => '/tmp/foo.log',
        );
        $this->twig
            ->expects($this->exactly(2))
            ->method('render')
            ->will(
                $this->returnValueMap(
                    array(
                        array('AkeneoBatchBundle:Mails:notification.txt.twig', $parameters, 'notification'),
                        array('AkeneoBatchBundle:Mails:notification.html.twig', $parameters, '<p>notification</p>'),
                    )
                )
            );

        $message = $this->getDisabledConstructorMock('\Swift_Message');
        $this->mailer
            ->expects($this->once())
            ->method('createMessage')
            ->will($this->returnValue($message));

        $message->expects($this->once())
            ->method('setSubject')
            ->with('Job has been executed');

        $message->expects($this->once())
            ->method('setFrom')
            ->with('no-reply@example.com');

        $user->expects($this->any())
            ->method('getEmail')
            ->will($this->returnValue('john.doe@example.com'));
        $message->expects($this->once())
            ->method('setTo')
            ->with('john.doe@example.com');
        $message->expects($this->once())
            ->method('setBody')
            ->with('notification', 'text/plain');
        $message->expects($this->once())
            ->method('addPart')
            ->with('<p>notification</p>', 'text/html');

        $this->mailer
            ->expects($this->once())
            ->method('send')
            ->with($message);

        $this->notifier->notify($jobExecution);
    }

    public function testNotifyIfRecipientEmailIsSet()
    {
        $this->handler
            ->expects($this->once())
            ->method('getFilename')
            ->will($this->returnValue('/tmp/foo.log'));

        $jobExecution = $this->getDisabledConstructorMock('Akeneo\Bundle\BatchBundle\Entity\JobExecution');
        $parameters = array(
            'jobExecution' => $jobExecution,
            'log'          => '/tmp/foo.log',
        );
        $this->twig
            ->expects($this->exactly(2))
            ->method('render')
            ->will(
                $this->returnValueMap(
                    array(
                        array('AkeneoBatchBundle:Mails:notification.txt.twig', $parameters, 'notification'),
                        array('AkeneoBatchBundle:Mails:notification.html.twig', $parameters, '<p>notification</p>'),
                    )
                )
            );

        $message = $this->getDisabledConstructorMock('\Swift_Message');
        $this->mailer
            ->expects($this->once())
            ->method('createMessage')
            ->will($this->returnValue($message));

        $message->expects($this->once())
            ->method('setSubject')
            ->with('Job has been executed');

        $message->expects($this->once())
            ->method('setFrom')
            ->with('no-reply@example.com');

        $message->expects($this->once())
            ->method('setTo')
            ->with('patricia.jekyll@example.com');
        $message->expects($this->once())
            ->method('setBody')
            ->with('notification', 'text/plain');
        $message->expects($this->once())
            ->method('addPart')
            ->with('<p>notification</p>', 'text/html');

        $this->mailer
            ->expects($this->once())
            ->method('send')
            ->with($message);

        $this->notifier->setRecipientEmail('patricia.jekyll@example.com');
        $this->notifier->notify($jobExecution);
    }

    public function testDoNotNotifyIfNoUserLoggedIn()
    {
        $token = $this->getTokenMock(null);

        $this->tokenStorage
            ->expects($this->once())
            ->method('getToken')
            ->will($this->returnValue($token));

        $jobExecution = $this->getDisabledConstructorMock('Akeneo\Bundle\BatchBundle\Entity\JobExecution');

        $this->mailer
            ->expects($this->never())
            ->method('send');

        $this->notifier->notify($jobExecution);
    }

    protected function getTokenMock($user)
    {
        $token = $this->getDisabledConstructorMock(
            'Symfony\Component\Security\Core\Authentication\Token\TokenInterface'
        );

        $token->expects($this->any())
              ->method('getUser')
              ->will($this->returnValue($user));

        return $token;
    }

    private function getUserMock()
    {
        return $this->getDisabledConstructorMock('Oro\Bundle\UserBundle\Entity\User');
    }

    private function getDisabledConstructorMock($classname)
    {
        return $this
            ->getMockBuilder($classname)
            ->disableOriginalConstructor()
            ->getMock();
    }
}
