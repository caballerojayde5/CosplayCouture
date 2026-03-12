<?php

namespace App\Controller;

use App\Entity\ActivityLog;
use App\Entity\Order;
use App\Entity\Delivery;
use App\Entity\Staff;
use App\Form\OrderType;
use App\Repository\CostumeRepository;
use App\Repository\OrderRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/orders')]
class OrderController extends AbstractController
{
    // 🧾 CUSTOMER VIEW (for users)
    #[Route('/', name: 'app_order_index', methods: ['GET'])]
    public function index(OrderRepository $orderRepository): Response
    {
        return $this->render('order/index.html.twig', [
            'orders' => $orderRepository->findAll(),
        ]);
    }

    // 👁️ SHOW ORDER
    #[Route('/{id}', name: 'app_order_show', methods: ['GET'])]
    public function show(Order $order): Response
    {
        return $this->render('order/show.html.twig', [
            'order' => $order,
        ]);
    }

    // ➕ ADD NEW ORDER
    #[Route('/new', name: 'app_order_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em, CostumeRepository $costumeRepository): Response
    {
        $order = new Order();
        $order->setCreatedAt(new \DateTimeImmutable());

        // Set createdBy if user is staff
        $user = $this->getUser();
        if ($user instanceof Staff) {
            $order->setCreatedBy($user);
        }

        $form = $this->createForm(OrderType::class, $order);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Set price from the selected costume
            $costume = $order->getCostume();
            if ($costume) {
                $order->setPrice($costume->getPrice());
            }

            // Save the order
            $em->persist($order);
            $em->flush();

            // Create a delivery record automatically
            $delivery = new Delivery();
            $delivery->setOrder($order);
            $delivery->setDeliveryStatus('Pending');
            $delivery->setDeliveryMethod('Courier'); // default
            $delivery->setDeliveryAddress($order->getAddress() ?? '');
            $em->persist($delivery);
            $em->flush();

            // Log activity if staff
            if ($user instanceof Staff) {
                $log = new ActivityLog();
                $log->setEventType('Staff creates a record');
                $log->setUser($user);
                $log->setDetails('Created order ID: ' . $order->getId());
                $em->persist($log);
                $em->flush();
            }

            $this->addFlash('success', '✅ Order created and delivery scheduled!');
            return $this->redirectToRoute('app_order_index');
        }

        return $this->render('order/new.html.twig', [
            'form' => $form,
            'costumes' => $costumeRepository->findAll(),
        ]);
    }

    // ✏️ EDIT ORDER
    #[Route('/{id}/edit', name: 'app_order_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Order $order, EntityManagerInterface $em, CostumeRepository $costumeRepository): Response
    {
        $user = $this->getUser();
        if ($user instanceof Staff && $order->getCreatedBy() !== $user) {
            throw $this->createAccessDeniedException('You can only edit your own records.');
        }

        $form = $this->createForm(OrderType::class, $order);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Set price from the selected costume
            $costume = $order->getCostume();
            if ($costume) {
                $order->setPrice($costume->getPrice());
            }

            $em->flush();

            // Log activity if staff
            if ($user instanceof Staff) {
                $log = new ActivityLog();
                $log->setEventType('Staff edits a record');
                $log->setUser($user);
                $log->setDetails('Edited order ID: ' . $order->getId());
                $em->persist($log);
                $em->flush();
            }

            $this->addFlash('success', '✏️ Order updated successfully!');
            return $this->redirectToRoute('app_order_index');
        }

        return $this->render('order/edit.html.twig', [
            'form' => $form,
            'order' => $order,
            'costumes' => $costumeRepository->findAll(),
        ]);
    }

    // 🗑️ DELETE ORDER
    #[Route('/{id}/delete', name: 'app_order_delete', methods: ['POST'])]
    public function delete(Request $request, Order $order, EntityManagerInterface $em): Response
    {
        $user = $this->getUser();
        if ($user instanceof Staff && $order->getCreatedBy() !== $user) {
            throw $this->createAccessDeniedException('You can only delete your own records.');
        }

        if ($this->isCsrfTokenValid('delete' . $order->getId(), $request->request->get('_token'))) {
            // Log activity if staff
            if ($user instanceof Staff) {
                $log = new ActivityLog();
                $log->setEventType('Staff deletes a record');
                $log->setUser($user);
                $log->setDetails('Deleted order ID: ' . $order->getId());
                $em->persist($log);
                $em->flush();
            }

            $em->remove($order);
            $em->flush();
            $this->addFlash('success', '🗑️ Order deleted successfully!');
        }

        return $this->redirectToRoute('app_order_index');
    }
}
