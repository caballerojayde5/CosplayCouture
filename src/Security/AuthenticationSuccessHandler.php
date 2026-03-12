<?php

namespace App\Security;

use App\Entity\ActivityLog;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface;
use Symfony\Component\HttpFoundation\Request;

class AuthenticationSuccessHandler implements AuthenticationSuccessHandlerInterface
{
    public function __construct(private RouterInterface $router, private EntityManagerInterface $em) {}

    public function onAuthenticationSuccess(
        Request $request,
        TokenInterface $token
    ): ?Response
    {
        $user = $token->getUser();

        // Log login activity
        $log = new ActivityLog();
        $log->setEventType('User login');
        $log->setUser($user);
        $log->setDetails('User logged in');
        $this->em->persist($log);
        $this->em->flush();

        $roles = $token->getRoleNames();

        if (in_array('ROLE_ADMIN', $roles, true)) {
            return new RedirectResponse(
                $this->router->generate('admin_dashboard')
            );
        }

        if (in_array('ROLE_STAFF', $roles, true)) {
            return new RedirectResponse(
                $this->router->generate('app_staff_index')
            );
        }

        return new RedirectResponse(
            $this->router->generate('app_login')
        );
    }
}
