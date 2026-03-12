<?php

namespace App\EventSubscriber;

use App\Entity\ActivityLog;
use Doctrine\Bundle\DoctrineBundle\EventSubscriber\EventSubscriberInterface;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Event\PostRemoveEventArgs;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use Doctrine\ORM\Events;
use Symfony\Bundle\SecurityBundle\Security;

class ActivityLogSubscriber implements EventSubscriberInterface
{
    private array $changes = [];

    public function __construct(
        private Security $security,
    ) {}

    public function getSubscribedEvents(): array
    {
        return [
            Events::postPersist,
            Events::postUpdate,
            Events::postRemove,
            Events::postFlush,
        ];
    }

    public function postPersist(PostPersistEventArgs $args): void
    {
        $entity = $args->getObject();
        $this->logChange($entity, 'created');
    }

    public function postUpdate(PostUpdateEventArgs $args): void
    {
        $entity = $args->getObject();
        $this->logChange($entity, 'updated');
    }

    public function postRemove(PostRemoveEventArgs $args): void
    {
        $entity = $args->getObject();
        $this->logChange($entity, 'deleted');
    }

    public function postFlush(PostFlushEventArgs $args): void
    {
        $em = $args->getEntityManager();

        foreach ($this->changes as $change) {
            $log = new ActivityLog();
            $log->setEventType($change['eventType']);
            $log->setUser($change['user']);
            $log->setDetails($change['details']);
            $em->persist($log);
        }

        $this->changes = [];
        $em->flush();
    }

    private function logChange(object $entity, string $action): void
    {
        $user = $this->security->getUser();

        if (!$user) {
            return; // Skip if no user (e.g., system operations)
        }

        $entityClass = get_class($entity);
        $entityId = method_exists($entity, 'getId') ? $entity->getId() : 'unknown';

        $eventType = ucfirst($action) . ' ' . (str_contains($entityClass, 'User') ? 'user' : 'record');
        $details = sprintf('%s %s: %s (ID: %s)', ucfirst($action), $this->getEntityName($entityClass), $entityClass, $entityId);

        $this->changes[] = [
            'eventType' => $eventType,
            'user' => $user,
            'details' => $details,
        ];
    }

    private function getEntityName(string $class): string
    {
        $parts = explode('\\', $class);
        return end($parts);
    }
}
