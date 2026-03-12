<?php

namespace App\Controller;

use App\Form\ChangePasswordType;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;

class StaffController extends AbstractController
{
    #[Route('/staff', name: 'app_staff_index')]
    public function index(UserRepository $userRepository): Response
    {
        // Get only staff users
        $allUsers = $userRepository->findAll();
        $staff = array_filter($allUsers, fn($u) => in_array('ROLE_STAFF', $u->getRoles()));

        return $this->render('staff/index.html.twig', [
            'staff' => $staff,
        ]);
    }

    #[Route('/staff/profile', name: 'staff_profile')]
    public function profile(): Response
    {
        return $this->render('staff/profile.html.twig', [
            'user' => $this->getUser(),
        ]);
    }

    #[Route('/staff/change-password', name: 'staff_change_password')]
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

            return $this->redirectToRoute('staff_profile');
        }

        return $this->render('staff/change_password.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/staff/costumes', name: 'staff_costumes_index')]
    public function costumesIndex(CostumeRepository $costumeRepository): Response
    {
        $user = $this->getUser();
        $costumes = $costumeRepository->findBy(['createdBy' => $user]);

        return $this->render('costume/index.html.twig', [
            'costumes' => $costumes,
        ]);
    }

    #[Route('/staff/custom-orders', name: 'staff_custom_orders_index')]
    public function customOrdersIndex(CustomOrderRepository $customOrderRepository): Response
    {
        $user = $this->getUser();
        $customOrders = $customOrderRepository->findBy(['createdBy' => $user]);

        return $this->render('custom_order/index.html.twig', [
            'custom_orders' => $customOrders,
        ]);
    }
}