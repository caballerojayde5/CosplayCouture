<?php

namespace App\Controller;

use App\Repository\CostumeRepository;
use App\Repository\OrderRepository;
use App\Repository\CustomOrderRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;

class ApiDataController extends AbstractController
{
    #[Route('/api/costumes', name: 'api_costumes', methods: ['GET'])]
    public function costumes(CostumeRepository $costumeRepository): JsonResponse
    {
        $costumes = $costumeRepository->findAll();
        $data = array_map(fn($c) => [
            'id'            => $c->getId(),
            'name'          => $c->getName(),
            'description'   => $c->getDescription(),
            'price'         => $c->getPrice(),
            'stockQuantity' => $c->getStockQuantity(),
            'category'      => $c->getCategory(),
            'imageUrl'      => $c->getImageUrl(),
        ], $costumes);

        return $this->json($data);
    }

    #[Route('/api/orders', name: 'api_orders', methods: ['GET'])]
    public function orders(OrderRepository $orderRepository): JsonResponse
    {
        $orders = $orderRepository->findAll();  
        $data = array_map(fn($o) => [
            'id'           => $o->getId(),
            'customerName' => $o->getCustomerName(),
            'email'        => $o->getEmail(),
            'status'       => $o->getStatus(),
            'price'        => $o->getPrice(),
            'address'      => $o->getAddress(),
            'createdAt'    => $o->getCreatedAt()?->format('Y-m-d H:i:s'),
            'costume'      => $o->getCostume() ? [
                'id'   => $o->getCostume()->getId(),
                'name' => $o->getCostume()->getName(),
            ] : null,
        ], $orders);

        return $this->json($data);
    }

    #[Route('/api/custom-orders', name: 'api_custom_orders', methods: ['GET'])]
    public function customOrders(CustomOrderRepository $customOrderRepository): JsonResponse
    {
        $orders = $customOrderRepository->findAll();
        $data = array_map(fn($o) => [
            'id'             => $o->getId(),
            'customerName'   => $o->getCustomerName(),
            'email'          => $o->getEmail(),
            'cosplayName'    => $o->getCosplayName(),
            'status'         => $o->getStatus(),
            'price'          => $o->getPrice(),
            'address'        => $o->getAddress(),
            'specialRequest' => $o->getSpecialRequest(),
            'createdAt'      => $o->getCreatedAt()?->format('Y-m-d H:i:s'),
        ], $orders);

        return $this->json($data);
    }

    #[Route('/api/dashboard', name: 'api_dashboard', methods: ['GET'])]
    public function dashboard(
        CostumeRepository $costumeRepository,
        OrderRepository $orderRepository,
        CustomOrderRepository $customOrderRepository
    ): JsonResponse {
        return $this->json([
            'totalCostumes'     => count($costumeRepository->findAll()),
            'totalOrders'       => count($orderRepository->findAll()),
            'totalCustomOrders' => count($customOrderRepository->findAll()),
        ]);
    }
    #[Route('/api/orders', name: 'api_orders_create', methods: ['POST'])]
public function createOrder(
    Request $request,
    CostumeRepository $costumeRepository,
    EntityManagerInterface $entityManager,
    \App\Repository\UserRepository $userRepository,
    \App\Service\FcmNotificationService $fcmService
): JsonResponse {
    $user = $this->getUser();
    $data = json_decode($request->getContent(), true);

    $costumeId    = $data['costumeId'] ?? null;
    $customerName = $data['customerName'] ?? null;
    $email        = $data['email'] ?? null;
    $address      = $data['address'] ?? null;
    $quantity     = $data['quantity'] ?? 1;

    if (!$costumeId || !$customerName || !$email) {
        return $this->json(['message' => 'Missing required fields'], 400);
    }

    $costume = $costumeRepository->find($costumeId);
    if (!$costume) return $this->json(['message' => 'Costume not found'], 404);
    if ($costume->getStockQuantity() <= 0) return $this->json(['message' => 'Out of stock'], 400);

    $order = new \App\Entity\Order();
    $order->setCustomerName($customerName);
    $order->setEmail($email);
    $order->setAddress($address);
    $order->setCostume($costume);
    $order->setPrice($costume->getPrice() * $quantity);
    $order->setStatus('Pending');
    $order->setCreatedAt(new \DateTimeImmutable());
    $order->setCreatedBy($user);

    $entityManager->persist($order);
    $entityManager->flush();

    // Notify all admins
    $admins = $userRepository->findAll();
    foreach ($admins as $admin) {
        if (in_array('ROLE_ADMIN', $admin->getRoles()) && $admin->getFcmToken()) {
            $fcmService->sendNotification(
                $admin->getFcmToken(),
                '🛒 New Order!',
                $customerName . ' ordered ' . $costume->getName(),
                ['channelId' => 'admin', 'orderId' => (string)$order->getId()]
            );
        }
    }

    // Notify customer
    if ($user->getFcmToken()) {
        $fcmService->sendNotification(
            $user->getFcmToken(),
            '✅ Order Placed!',
            'Your order for ' . $costume->getName() . ' has been placed!',
            ['channelId' => 'orders', 'orderId' => (string)$order->getId()]
        );
    }

    return $this->json(['message' => 'Order placed successfully', 'order' => ['id' => $order->getId()]], 201);
}
public function myOrders(OrderRepository $orderRepository): JsonResponse
{
    $user = $this->getUser();
    $orders = $orderRepository->findBy(['createdBy' => $user]);
    $data = array_map(fn($o) => [
        'id'           => $o->getId(),
        'customerName' => $o->getCustomerName(),
        'email'        => $o->getEmail(),
        'status'       => $o->getStatus(),
        'price'        => $o->getPrice(),
        'address'      => $o->getAddress(),
        'createdAt'    => $o->getCreatedAt()?->format('Y-m-d H:i:s'),
        'costume'      => $o->getCostume() ? [
            'id'   => $o->getCostume()->getId(),
            'name' => $o->getCostume()->getName(),
        ] : null,
    ], $orders);

    return $this->json($data);
}
#[Route('/api/orders/{id}/cancel', name: 'api_order_cancel', methods: ['POST'])]
public function cancelOrder(
    int $id,
    OrderRepository $orderRepository,
    EntityManagerInterface $entityManager
): JsonResponse {
    $user = $this->getUser();
    $order = $orderRepository->find($id);

    if (!$order) {
        return $this->json(['message' => 'Order not found'], 404);
    }

    if ($order->getCreatedBy() !== $user) {
        return $this->json(['message' => 'Unauthorized'], 403);
    }

    if ($order->getStatus() !== 'Pending') {
        return $this->json(['message' => 'Only pending orders can be cancelled'], 400);
    }

    $order->setStatus('Cancelled');
    $entityManager->flush();

    return $this->json(['message' => 'Order cancelled successfully']);
}
// ===== ADMIN: UPDATE ORDER STATUS =====
#[Route('/api/admin/orders/{id}/status', name: 'api_admin_order_status', methods: ['POST'])]
public function updateOrderStatus(
    int $id,
    Request $request,
    OrderRepository $orderRepository,
    EntityManagerInterface $entityManager,
    \App\Service\FcmNotificationService $fcmService
): JsonResponse {
    $order = $orderRepository->find($id);
    if (!$order) return $this->json(['message' => 'Order not found'], 404);

    $data = json_decode($request->getContent(), true);
    $status = $data['status'] ?? $order->getStatus();
    $order->setStatus($status);
    $entityManager->flush();

    // Notify customer
    $customer = $order->getCreatedBy();
    if ($customer && $customer->getFcmToken()) {
        $fcmService->sendNotification(
            $customer->getFcmToken(),
            '📦 Order Update!',
            'Your order for ' . $order->getCostume()->getName() . ' is now ' . $status,
            ['channelId' => 'orders', 'orderId' => (string)$order->getId()]
        );
    }

    return $this->json(['message' => 'Status updated']);
}

// ===== ADMIN: DELETE ORDER =====
#[Route('/api/admin/orders/{id}', name: 'api_admin_order_delete', methods: ['DELETE'])]
public function deleteOrder(
    int $id,
    OrderRepository $orderRepository,
    EntityManagerInterface $entityManager
): JsonResponse {
    $order = $orderRepository->find($id);
    if (!$order) return $this->json(['message' => 'Order not found'], 404);

    $entityManager->remove($order);
    $entityManager->flush();

    return $this->json(['message' => 'Order deleted']);
}

// ===== ADMIN: CREATE COSTUME =====
#[Route('/api/admin/costumes', name: 'api_admin_costume_create', methods: ['POST'])]
public function createCostume(
    Request $request,
    EntityManagerInterface $entityManager
): JsonResponse {
    $data = json_decode($request->getContent(), true);

    $costume = new \App\Entity\Costume();
    $costume->setName($data['name'] ?? '');
    $costume->setDescription($data['description'] ?? '');
    $costume->setPrice($data['price'] ?? 0);
    $costume->setStockQuantity($data['stockQuantity'] ?? 0);
    $costume->setCategory($data['category'] ?? '');
    $costume->setImageUrl($data['imageUrl'] ?? null);
    $costume->setCreatedBy($this->getUser());

    $entityManager->persist($costume);
    $entityManager->flush();

    return $this->json(['message' => 'Costume created', 'id' => $costume->getId()], 201);
}

// ===== ADMIN: UPDATE COSTUME =====
#[Route('/api/admin/costumes/{id}', name: 'api_admin_costume_update', methods: ['PUT'])]
public function updateCostume(
    int $id,
    Request $request,
    CostumeRepository $costumeRepository,
    EntityManagerInterface $entityManager
): JsonResponse {
    $costume = $costumeRepository->find($id);
    if (!$costume) return $this->json(['message' => 'Costume not found'], 404);

    $data = json_decode($request->getContent(), true);
    if (isset($data['name'])) $costume->setName($data['name']);
    if (isset($data['description'])) $costume->setDescription($data['description']);
    if (isset($data['price'])) $costume->setPrice($data['price']);
    if (isset($data['stockQuantity'])) $costume->setStockQuantity($data['stockQuantity']);
    if (isset($data['category'])) $costume->setCategory($data['category']);
    if (isset($data['imageUrl'])) $costume->setImageUrl($data['imageUrl']);

    $entityManager->flush();

    return $this->json(['message' => 'Costume updated']);
}

// ===== ADMIN: DELETE COSTUME =====
#[Route('/api/admin/costumes/{id}', name: 'api_admin_costume_delete', methods: ['DELETE'])]
public function deleteCostume(
    int $id,
    CostumeRepository $costumeRepository,
    EntityManagerInterface $entityManager
): JsonResponse {
    $costume = $costumeRepository->find($id);
    if (!$costume) return $this->json(['message' => 'Costume not found'], 404);

    $entityManager->remove($costume);
    $entityManager->flush();

    return $this->json(['message' => 'Costume deleted']);
}

// ===== ADMIN: GET USERS =====
#[Route('/api/admin/users', name: 'api_admin_users', methods: ['GET'])]
public function getUsers(\App\Repository\UserRepository $userRepository): JsonResponse
{
    $users = $userRepository->findAll();
    $data = array_map(fn($u) => [
        'id'         => $u->getId(),
        'email'      => $u->getEmail(),
        'roles'      => $u->getRoles(),
        'type'       => $u->getType(),
        'isVerified' => $u->isVerified(),
    ], $users);

    return $this->json($data);
}

// ===== ADMIN: DELETE USER =====
#[Route('/api/admin/users/{id}', name: 'api_admin_user_delete', methods: ['DELETE'])]
public function deleteUser(
    int $id,
    \App\Repository\UserRepository $userRepository,
    EntityManagerInterface $entityManager
): JsonResponse {
    $user = $userRepository->find($id);
    if (!$user) return $this->json(['message' => 'User not found'], 404);

    $entityManager->remove($user);
    $entityManager->flush();

    return $this->json(['message' => 'User deleted']);
}
#[Route('/api/save-fcm-token', name: 'api_save_fcm_token', methods: ['POST'])]
public function saveFcmToken(
    Request $request,
    EntityManagerInterface $entityManager
): JsonResponse {
    $user = $this->getUser();
    $data = json_decode($request->getContent(), true);
    $token = $data['fcm_token'] ?? null;

    if (!$token) return $this->json(['message' => 'Token required'], 400);

    $user->setFcmToken($token);
    $entityManager->flush();

    return $this->json(['message' => 'Token saved']);
}
// Customer sends message
#[Route('/api/messages', name: 'api_messages_create', methods: ['POST'])]
public function createMessage(
    Request $request,
    EntityManagerInterface $entityManager
): JsonResponse {
    $user = $this->getUser();
    $data = json_decode($request->getContent(), true);

    $msg = new \App\Entity\Message();
    $msg->setName($data['name'] ?? $user->getEmail());
    $msg->setEmail($user->getEmail());
    $msg->setSubject($data['subject'] ?? 'General Inquiry');
    $msg->setMessage($data['message'] ?? '');
    $msg->setIsRead(false);
    $msg->setCreatedAt(new \DateTimeImmutable());
    $msg->setCreatedBy($user);

    $entityManager->persist($msg);
    $entityManager->flush();

    return $this->json(['message' => 'Message sent', 'id' => $msg->getId()], 201);
}

// Customer gets their messages
#[Route('/api/messages', name: 'api_messages_list', methods: ['GET'])]
public function getMyMessages(
    \App\Repository\MessageRepository $messageRepository
): JsonResponse {
    $user = $this->getUser();
    $messages = $messageRepository->findBy(['createdBy' => $user], ['createdAt' => 'DESC']);
    $data = array_map(fn($m) => [
        'id'        => $m->getId(),
        'name'      => $m->getName(),
        'subject'   => $m->getSubject(),
        'message'   => $m->getMessage(),
        'reply'     => $m->getReply(),
        'isRead'    => $m->isRead(),
        'createdAt' => $m->getCreatedAt()?->format('Y-m-d H:i:s'),
    ], $messages);

    return $this->json($data);
}

// Admin gets all messages
#[Route('/api/admin/messages', name: 'api_admin_messages', methods: ['GET'])]
public function getAdminMessages(
    \App\Repository\MessageRepository $messageRepository
): JsonResponse {
    $messages = $messageRepository->findBy([], ['createdAt' => 'DESC']);
    $data = array_map(fn($m) => [
        'id'        => $m->getId(),
        'name'      => $m->getName(),
        'email'     => $m->getEmail(),
        'subject'   => $m->getSubject(),
        'message'   => $m->getMessage(),
        'reply'     => $m->getReply(),
        'isRead'    => $m->isRead(),
        'createdAt' => $m->getCreatedAt()?->format('Y-m-d H:i:s'),
    ], $messages);

    return $this->json($data);
}

// Admin replies to message
#[Route('/api/admin/messages/{id}/reply', name: 'api_admin_message_reply', methods: ['POST'])]
public function replyMessage(
    int $id,
    Request $request,
    \App\Repository\MessageRepository $messageRepository,
    EntityManagerInterface $entityManager
): JsonResponse {
    $msg = $messageRepository->find($id);
    if (!$msg) return $this->json(['message' => 'Message not found'], 404);

    $data = json_decode($request->getContent(), true);
    $msg->setReply($data['reply'] ?? '');
    $msg->setIsRead(true);
    $entityManager->flush();

    return $this->json(['message' => 'Reply sent']);
}
}