<?php

namespace App\Controller;

use App\Entity\ActivityLog;
use App\Entity\Costume;
use App\Entity\Staff;
use App\Form\CostumeType;
use App\Repository\ActivityLogRepository;
use App\Repository\CostumeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/costume')]
final class CostumeController extends AbstractController
{
    #[Route('/', name: 'app_costume_index', methods: ['GET'])]
    public function index(CostumeRepository $costumeRepository): Response
    {
        $user = $this->getUser();
        if ($user instanceof Staff) {
            $costumes = $costumeRepository->findByCreatedBy($user);
        } else {
            $costumes = $costumeRepository->findAll();
        }

        return $this->render('costume/index.html.twig', [
            'costumes' => $costumes,
        ]);
    }

   #[Route('/admin', name: 'app_costume_admin', methods: ['GET'])]
    public function adminHome(): Response
{
    return $this->render('admin/index.html.twig');
}


    #[Route('/new', name: 'app_costume_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, ActivityLogRepository $activityLogRepository): Response
    {
        $costume = new Costume();
        $user = $this->getUser();
        if ($user instanceof Staff) {
            $costume->setCreatedBy($user);
        }
        $form = $this->createForm(CostumeType::class, $costume);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($costume);
            $entityManager->flush();

            // Log activity
            $log = new ActivityLog();
            $log->setEventType('Staff creates record');
            $log->setUser($user);
            $log->setDetails('Created costume: ' . $costume->getName());
            $this->entityManager->persist($log);
            $this->entityManager->flush();

            return $this->redirectToRoute('app_costume_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('costume/new.html.twig', [
            'costume' => $costume,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_costume_show', methods: ['GET'])]
    public function show(Costume $costume): Response
    {
        return $this->render('costume/show.html.twig', [
            'costume' => $costume,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_costume_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Costume $costume, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(CostumeType::class, $costume);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_costume_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('costume/edit.html.twig', [
            'costume' => $costume,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_costume_delete', methods: ['POST'])]
    public function delete(Request $request, Costume $costume, EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();
        if ($user instanceof Staff && $costume->getCreatedBy() !== $user) {
            throw $this->createAccessDeniedException('You can only delete your own records.');
        }

        if ($this->isCsrfTokenValid('delete' . $costume->getId(), $request->request->get('_token'))) {
            // Log activity if staff
            if ($user instanceof Staff) {
                $log = new ActivityLog();
                $log->setEventType('Staff deletes a record');
                $log->setUser($user);
                $log->setDetails('Deleted costume: ' . $costume->getName());
                $entityManager->persist($log);
                $entityManager->flush();
            }

            $entityManager->remove($costume);
            $entityManager->flush();
            $this->addFlash('success', 'Costume deleted successfully.');
        }

        return $this->redirectToRoute('app_costume_index');
    }
}
