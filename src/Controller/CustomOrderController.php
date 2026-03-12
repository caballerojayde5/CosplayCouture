<?php

namespace App\Controller;

use App\Entity\ActivityLog;
use App\Entity\CustomOrder;
use App\Entity\Delivery;
use App\Entity\Staff;
use App\Form\CustomOrderType;
use App\Repository\CustomOrderRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/custom-orders')]
final class CustomOrderController extends AbstractController
{
    // 🧾 CUSTOMER VIEW (for users)
    #[Route('/', name: 'app_custom_order_index', methods: ['GET'])]
    public function index(CustomOrderRepository $customOrderRepository): Response
    {
        return $this->render('custom_order/index.html.twig', [
            'custom_orders' => $customOrderRepository->findAll(),
        ]);
    }

    // ➕ CUSTOMER NEW CUSTOM ORDER
    #[Route('/new', name: 'app_custom_order_new', methods: ['GET', 'POST'])]
    public function customerNew(Request $request, EntityManagerInterface $em): Response
    {
        $customOrder = new CustomOrder();
        $customOrder->setCreatedAt(new \DateTimeImmutable());

        // Set createdBy if user is staff
        $user = $this->getUser();
        if ($user instanceof Staff) {
            $customOrder->setCreatedBy($user);
        }

        $form = $this->createForm(CustomOrderType::class, $customOrder);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Save the custom order
            $em->persist($customOrder);
            $em->flush();

            // Create a delivery record automatically
            $delivery = new Delivery();
            $delivery->setCustomOrder($customOrder);
            $delivery->setDeliveryStatus('Pending');
            $delivery->setDeliveryMethod('Courier'); // default
            $delivery->setDeliveryAddress($customOrder->getAddress() ?? '');
            $em->persist($delivery);
            $em->flush();

            // Log activity if staff
            if ($user instanceof Staff) {
                $log = new ActivityLog();
                $log->setEventType('Staff creates a record');
                $log->setUser($user);
                $log->setDetails('Created custom order ID: ' . $customOrder->getId());
                $em->persist($log);
                $em->flush();
            }

            $this->addFlash('success', '✅ Custom order created and delivery scheduled!');
            return $this->redirectToRoute('app_custom_order_index');
        }

        return $this->render('custom_order/new.html.twig', [
            'form' => $form,
        ]);
    }

    // 👁️ CUSTOMER SHOW ORDER
    #[Route('/{id}', name: 'app_custom_order_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function customerShow(string $id, CustomOrderRepository $customOrderRepository): Response
    {
        $id = (int) $id;
        $customOrder = $customOrderRepository->find($id);

        if (!$customOrder) {
            $this->addFlash('error', 'Custom order not found.');
            return $this->redirectToRoute('app_custom_order_index');
        }

        return $this->render('custom_order/show.html.twig', [
            'custom_order' => $customOrder,
        ]);
    }

    // ✏️ CUSTOMER EDIT ORDER
    #[Route('/{id}/edit', name: 'app_custom_order_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function customerEdit(Request $request, string $id, CustomOrderRepository $customOrderRepository, EntityManagerInterface $entityManager): Response
    {
        $id = (int) $id;
        $customOrder = $customOrderRepository->find($id);

        if (!$customOrder) {
            $this->addFlash('error', 'Custom order not found.');
            return $this->redirectToRoute('app_custom_order_index');
        }

        $user = $this->getUser();
        if ($user instanceof Staff && $customOrder->getCreatedBy() !== $user) {
            throw $this->createAccessDeniedException('You can only edit your own records.');
        }

        $form = $this->createForm(CustomOrderType::class, $customOrder);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();
            $this->addFlash('success', '✏️ Order updated successfully!');
            return $this->redirectToRoute('app_custom_order_index');
        }

        return $this->render('custom_order/edit.html.twig', [
            'form' => $form->createView(),
            'custom_order' => $customOrder,
        ]);
    }

    // 🗑️ CUSTOMER DELETE ORDER
    #[Route('/{id}/delete', name: 'app_custom_order_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function customerDelete(Request $request, string $id, CustomOrderRepository $customOrderRepository, EntityManagerInterface $entityManager): Response
    {
        $id = (int) $id;
        $customOrder = $customOrderRepository->find($id);

        if (!$customOrder) {
            $this->addFlash('error', 'Custom order not found.');
            return $this->redirectToRoute('app_custom_order_index');
        }

        if ($this->isCsrfTokenValid('delete' . $customOrder->getId(), $request->request->get('_token'))) {
            $user = $this->getUser();
            if ($user instanceof Staff && $customOrder->getCreatedBy() !== $user) {
                throw $this->createAccessDeniedException('You can only delete your own records.');
            }

            // Log activity if staff
            if ($user instanceof Staff) {
                $log = new ActivityLog();
                $log->setEventType('Staff deletes a record');
                $log->setUser($user);
                $log->setDetails('Deleted custom order ID: ' . $customOrder->getId());
                $entityManager->persist($log);
                $entityManager->flush();
            }

            $entityManager->remove($customOrder);
            $entityManager->flush();
            $this->addFlash('success', '🗑️ Custom order deleted successfully!');
        }

        return $this->redirectToRoute('app_custom_order_index');
    }

    // 🪄 ADMIN VIEW (for dashboard)
    #[Route('/admin', name: 'admin_custom_orders', methods: ['GET'])]
    public function adminList(CustomOrderRepository $repo): Response
    {
        $orders = $repo->findAll();

        return $this->render('admin/custom_order/index.html.twig', [
            'orders' => $orders,
        ]);
    }

    // ➕ ADD NEW CUSTOM ORDER
    #[Route('/admin/new', name: 'admin_custom_order_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $customOrder = new CustomOrder();
        $customOrder->setCreatedAt(new \DateTimeImmutable());

        $form = $this->createForm(CustomOrderType::class, $customOrder);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Save the custom order
            $em->persist($customOrder);
            $em->flush();

            // Create a delivery record automatically
            $delivery = new Delivery();
            $delivery->setCustomOrder($customOrder);
            $delivery->setDeliveryStatus('Pending');
            $delivery->setDeliveryMethod('Courier'); // default
            $delivery->setDeliveryAddress($customOrder->getAddress() ?? '');
            $em->persist($delivery);
            $em->flush();

            $this->addFlash('success', '✅ Custom order created and delivery scheduled!');
            return $this->redirectToRoute('admin_custom_orders');
        }

        return $this->render('admin/custom_order/new.html.twig', [
            'form' => $form,
        ]);
    }

    // 👁️ SHOW ORDER
    #[Route('/admin/{id}', name: 'admin_custom_order_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(string $id, CustomOrderRepository $customOrderRepository): Response
    {
        $id = (int) $id;
        $customOrder = $customOrderRepository->find($id);

        if (!$customOrder) {
            $this->addFlash('error', 'Custom order not found.');
            return $this->redirectToRoute('admin_custom_orders');
        }

        return $this->render('admin/custom_order/show.html.twig', [
            'custom_order' => $customOrder,
        ]);
    }

    // ✏️ EDIT ORDER
    #[Route('/admin/{id}/edit', name: 'admin_custom_order_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function edit(Request $request, string $id, CustomOrderRepository $customOrderRepository, EntityManagerInterface $entityManager): Response
    {
        $id = (int) $id;
        $customOrder = $customOrderRepository->find($id);

        if (!$customOrder) {
            $this->addFlash('error', 'Custom order not found.');
            return $this->redirectToRoute('admin_custom_orders');
        }

        $form = $this->createForm(CustomOrderType::class, $customOrder);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();
            $this->addFlash('success', '✏️ Order updated successfully!');
            return $this->redirectToRoute('admin_custom_orders');
        }

        return $this->render('admin/custom_order/edit.html.twig', [
            'form' => $form->createView(),
            'custom_order' => $customOrder,
        ]);
    }

    // 🗑️ DELETE ORDER
    #[Route('/admin/{id}/delete', name: 'admin_custom_order_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(Request $request, string $id, CustomOrderRepository $customOrderRepository, EntityManagerInterface $entityManager): Response
    {
        $id = (int) $id;
        $customOrder = $customOrderRepository->find($id);

        if (!$customOrder) {
            $this->addFlash('error', 'Custom order not found.');
            return $this->redirectToRoute('admin_custom_orders');
        }

        if ($this->isCsrfTokenValid('delete' . $customOrder->getId(), $request->request->get('_token'))) {
            $entityManager->remove($customOrder);
            $entityManager->flush();
            $this->addFlash('success', '🗑️ Custom order deleted successfully!');
        }

        return $this->redirectToRoute('admin_custom_orders');
    }
}