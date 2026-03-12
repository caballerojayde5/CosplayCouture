<?php

namespace App\Security;

use App\Entity\ActivityLog;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Security\Http\Event\LogoutEvent;

#[AsEventListener(event: LogoutEvent::class)]
class LogoutEventListener
{
    public function __construct(private EntityManagerInterface $em) {}

    public function __invoke(LogoutEvent $event): void
    {
        $user = $event->getToken()->getUser();

        // Log logout activity
        $log = new ActivityLog();
        $log->setEventType('User logout');
        $log->setUser($user);
        $log->setDetails('User logged out');
        $this->em->persist($log);
        $this->em->flush();
    }
}
