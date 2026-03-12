<?php

namespace App\Controller;

use App\Entity\User;
use App\Entity\ActivityLog;
use App\Form\ChangePasswordType;
use App\Repository\UserRepository;
use App\Repository\CostumeRepository;
use App\Repository\CustomOrderRepository;
use App\Repository\ActivityLogRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

class AdminController extends AbstractController
{
    #[Route('/admin', name: 'admin_dashboard')]
    public function index(
        UserRepository $userRepo,
        CostumeRepository $costumeRepo,
        CustomOrderRepository $customOrderRepo
    ): Response {
        $allUsers = $userRepo->findAll();
        $admins = array_filter($allUsers, fn($u) => in_array('ROLE_ADMIN', $u->getRoles()));
        $staff = array_filter($allUsers, fn($u) => in_array('ROLE_STAFF', $u->getRoles()));

        return $this->render('admin/index.html.twig', [
            'totalAdmins' => count($admins),
            'totalStaff' => count($staff),
            'totalCostumes' => count($costumeRepo->findAll()),
            'totalCustomOrders' => count($customOrderRepo->findAll()),
        ]);
    }
 
    #[Route('/admin/activity-logs', name: 'admin_activity_logs')]
    public function activityLogs(ActivityLogRepository $activityLogRepo): Response
    {
        $logs = $activityLogRepo->findBy([], ['timestamp' => 'DESC']);

        return $this->render('admin/activity_logs/index.html.twig', [
            'logs' => $logs,
        ]);
    }

    #[Route('/admin/profile', name: 'admin_profile')]
    public function profile(): Response
    {
        return $this->render('admin/profile.html.twig', [
            'user' => $this->getUser(),
        ]);
    }

    #[Route('/admin/change-password', name: 'admin_change_password')]
    public function changePassword(
        Request $request,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $entityManager
    ): Response {
        $user = $this->getUser();
        $form = $this->createForm(ChangePasswordType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $hashedPassword = $passwordHasher->hashPassword($user, $data['newPassword']);
            $user->setPassword($hashedPassword);

            $entityManager->flush();

            $this->addFlash('success', 'Password changed successfully!');

            return $this->redirectToRoute('admin_profile');
        }

        return $this->render('admin/change_password.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/admin/users', name: 'admin_users_index')]
    public function usersIndex(UserRepository $userRepo): Response
    {
        $allUsers = $userRepo->findAll();
        $admins = array_filter($allUsers, fn($u) => in_array('ROLE_ADMIN', $u->getRoles()));
        $staffs = array_filter($allUsers, fn($u) => in_array('ROLE_STAFF', $u->getRoles()));

        return $this->render('admin/user/index.html.twig', [
            'admins' => $admins,
            'staffs' => $staffs,
        ]);
    }

    #[Route('/admin/users/new', name: 'admin_users_new')]
    public function usersNew(
        Request $request,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $entityManager,
        LoggerInterface $logger
    ): Response {
        $form = $this->createFormBuilder()
            ->add('email', EmailType::class)
            ->add('password', PasswordType::class)
            ->add('role', ChoiceType::class, [
                'choices' => [
                    'Admin' => 'admin',
                    'Staff' => 'staff',
                ],
            ])
            ->add('save', SubmitType::class, ['label' => 'Create User'])
            ->getForm();

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();

            $user = new User();
            $user->setEmail($data['email']);
            $user->setType($data['role']);
            
            // Set roles based on type
            if ($data['role'] === 'admin') {
                $user->setRoles(['ROLE_ADMIN']);
            } else {
                $user->setRoles(['ROLE_STAFF']);
            }
            
            $hashedPassword = $passwordHasher->hashPassword($user, $data['password']);
            $user->setPassword($hashedPassword);

            $entityManager->persist($user);
            $entityManager->flush();

            // Log activity
            $log = new ActivityLog();
            $log->setEventType('Admin creates a user');
            $log->setUser($this->getUser());
            $log->setDetails('Created user: ' . $user->getEmail() . ' (' . $data['role'] . ')');
            $entityManager->persist($log);
            $entityManager->flush();

            $logger->info('User created', [
                'action' => 'create',
                'user_id' => $user->getId(),
                'user_email' => $user->getEmail(),
                'user_role' => $data['role'],
                'performed_by' => $this->getUser()->getEmail(),
            ]);

            $this->addFlash('success', 'User created successfully!');

            return $this->redirectToRoute('admin_users_index');
        }

        return $this->render('admin/user/new.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/admin/users/{id}/edit', name: 'admin_users_edit')]
    public function usersEdit(
        Request $request,
        int $id,
        UserRepository $userRepo,
        EntityManagerInterface $entityManager
    ): Response {
        $user = $userRepo->find($id);

        if (!$user) {
            throw $this->createNotFoundException('User not found');
        }

        $currentRole = in_array('ROLE_ADMIN', $user->getRoles()) ? 'admin' : 'staff';

        $form = $this->createFormBuilder($user)
            ->add('email', EmailType::class)
            ->add('role', ChoiceType::class, [
                'choices' => [
                    'Admin' => 'admin',
                    'Staff' => 'staff',
                ],
                'data' => $currentRole,
                'mapped' => false,
            ])
            ->add('save', SubmitType::class, ['label' => 'Update User'])
            ->getForm();

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $newRole = $form->get('role')->getData();

            // Update role
            if ($newRole === 'admin') {
                $user->setRoles(['ROLE_ADMIN']);
                $user->setType('admin');
            } else {
                $user->setRoles(['ROLE_STAFF']);
                $user->setType('staff');
            }

            $entityManager->flush();

            // Log activity
            $log = new ActivityLog();
            $log->setEventType('Admin updates any record');
            $log->setUser($this->getUser());
            $log->setDetails('Updated user: ' . $user->getEmail());
            $entityManager->persist($log);
            $entityManager->flush();

            $this->addFlash('success', 'User updated successfully!');

            return $this->redirectToRoute('admin_users_index');
        }

        return $this->render('admin/user/edit.html.twig', [
            'form' => $form->createView(),
            'user' => $user,
        ]);
    }

    #[Route('/admin/users/{id}/reset-password', name: 'admin_users_reset_password')]
    public function usersResetPassword(
        Request $request,
        int $id,
        UserRepository $userRepo,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $entityManager,
        LoggerInterface $logger
    ): Response {
        $user = $userRepo->find($id);

        if (!$user) {
            throw $this->createNotFoundException('User not found');
        }

        $form = $this->createFormBuilder()
            ->add('newPassword', PasswordType::class)
            ->add('save', SubmitType::class, ['label' => 'Reset Password'])
            ->getForm();

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $hashedPassword = $passwordHasher->hashPassword($user, $data['newPassword']);
            $user->setPassword($hashedPassword);

            $entityManager->flush();

            $logger->info('User password reset', [
                'action' => 'reset_password',
                'user_id' => $user->getId(),
                'user_email' => $user->getEmail(),
                'performed_by' => $this->getUser()->getEmail(),
            ]);

            $this->addFlash('success', 'Password reset successfully!');

            return $this->redirectToRoute('admin_users_index');
        }

        return $this->render('admin/user/reset_password.html.twig', [
            'form' => $form->createView(),
            'user' => $user,
        ]);
    }

    #[Route('/admin/users/{id}/delete', name: 'admin_users_delete', methods: ['POST'])]
    public function usersDelete(
        Request $request,
        int $id,
        UserRepository $userRepo,
        EntityManagerInterface $entityManager
    ): Response {
        $user = $userRepo->find($id);

        if (!$user) {
            throw $this->createNotFoundException('User not found');
        }

        if ($this->isCsrfTokenValid('delete'.$user->getId(), $request->request->get('_token'))) {
            $userType = in_array('ROLE_ADMIN', $user->getRoles()) ? 'admin' : 'staff';

            // Log activity before deleting
            $log = new ActivityLog();
            $log->setEventType('Admin deletes a user');
            $log->setUser($this->getUser());
            $log->setDetails('Deleted user: ' . $user->getEmail() . ' (' . $userType . ')');
            $entityManager->persist($log);

            $entityManager->remove($user);
            $entityManager->flush();

            $this->addFlash('success', 'User deleted successfully!');
        }

        return $this->redirectToRoute('admin_users_index');
    }

   
}
