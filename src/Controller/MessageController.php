<?php

namespace App\Controller;

use App\Entity\Message;
use App\Repository\MessageRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin/messages')]
class MessageController extends AbstractController
{
    #[Route('/', name: 'app_message_index', methods: ['GET'])]
    public function index(MessageRepository $repo): Response
    {
        $messages = $repo->findBy([], ['createdAt' => 'DESC']);
        return $this->render('admin/message/index.html.twig', [
            'messages' => $messages,
        ]);
    }

    #[Route('/{id}', name: 'app_message_show', methods: ['GET', 'POST'])]
    public function show(Message $message, Request $request, EntityManagerInterface $em): Response
    {
        if ($request->isMethod('POST')) {
            $reply = $request->request->get('reply');
            $message->setReply($reply);
            $message->setIsRead(true);
            $em->flush();
            $this->addFlash('success', 'Reply sent!');
            return $this->redirectToRoute('app_message_show', ['id' => $message->getId()]);
        }

        return $this->render('admin/message/show.html.twig', [
            'message' => $message,
        ]);
    }

    // Staff CRUD for Messages
    #[Route('/staff/new', name: 'app_message_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $message = new Message();
        $message->setCreatedAt(new \DateTimeImmutable());
        $message->setIsRead(false);

        // Set createdBy if user is staff
        $user = $this->getUser();
        if ($user instanceof Staff) {
            $message->setCreatedBy($user);
        }

        if ($request->isMethod('POST')) {
            $message->setName($request->request->get('name'));
            $message->setEmail($request->request->get('email'));
            $message->setSubject($request->request->get('subject'));
            $message->setMessage($request->request->get('message'));

            $em->persist($message);
            $em->flush();
            $this->addFlash('success', 'Message sent!');
            return $this->redirectToRoute('app_message_index');
        }

        return $this->render('message/index.html.twig'); // Assuming a form template
    }

    #[Route('/staff/{id}/edit', name: 'app_message_edit', methods: ['GET', 'POST'])]
    public function edit(Message $message, Request $request, EntityManagerInterface $em): Response
    {
        $user = $this->getUser();
        if ($user instanceof Staff && $message->getCreatedBy() !== $user) {
            throw $this->createAccessDeniedException('You can only edit your own messages.');
        }

        if ($request->isMethod('POST')) {
            $message->setName($request->request->get('name'));
            $message->setEmail($request->request->get('email'));
            $message->setSubject($request->request->get('subject'));
            $message->setMessage($request->request->get('message'));

            $em->flush();
            $this->addFlash('success', 'Message updated!');
            return $this->redirectToRoute('app_message_index');
        }

        return $this->render('message/index.html.twig'); // Assuming a form template
    }

    #[Route('/staff/{id}/delete', name: 'app_message_delete', methods: ['POST'])]
    public function delete(Message $message, Request $request, EntityManagerInterface $em): Response
    {
        $user = $this->getUser();
        if ($user instanceof Staff && $message->getCreatedBy() !== $user) {
            throw $this->createAccessDeniedException('You can only delete your own messages.');
        }

        if ($this->isCsrfTokenValid('delete' . $message->getId(), $request->request->get('_token'))) {
            $em->remove($message);
            $em->flush();
            $this->addFlash('success', 'Message deleted!');
        }

        return $this->redirectToRoute('app_message_index');
    }
}
