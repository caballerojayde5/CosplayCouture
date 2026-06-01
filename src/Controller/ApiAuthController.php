<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

class ApiAuthController extends AbstractController
{
    #[Route('/api/login', name: 'api_login', methods: ['POST'])]
    public function login(
        Request $request,
        UserRepository $userRepository,
        UserPasswordHasherInterface $passwordHasher,
        JWTTokenManagerInterface $jwtManager
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);
        $email    = $data['email'] ?? null;
        $password = $data['password'] ?? null;

        if (!$email || !$password) {
            return $this->json(['message' => 'Email and password are required'], 400);
        }

        $user = $userRepository->findOneBy(['email' => $email]);

        if (!$user || !$passwordHasher->isPasswordValid($user, $password)) {
            return $this->json(['message' => 'Invalid credentials'], 401);
        }

        if (!$user->isVerified()) {
            return $this->json(['message' => 'Please verify your email first'], 403);
        }

        $token = $jwtManager->create($user);

        return $this->json([
            'token' => $token,
            'user'  => [
                'id'    => $user->getId(),
                'email' => $user->getEmail(),
                'type'  => $user->getType(),
                'roles' => $user->getRoles(),
            ]
        ]);
    }

    #[Route('/api/register', name: 'api_register', methods: ['POST'])]
    public function register(
        Request $request,
        UserRepository $userRepository,
        UserPasswordHasherInterface $passwordHasher,
        JWTTokenManagerInterface $jwtManager,
        EntityManagerInterface $entityManager
    ): JsonResponse {
        $data     = json_decode($request->getContent(), true);
        $email    = $data['email'] ?? null;
        $password = $data['password'] ?? null;

        if (!$email || !$password) {
            return $this->json(['message' => 'Email and password are required'], 400);
        }

        $existing = $userRepository->findOneBy(['email' => $email]);
        if ($existing) {
            return $this->json(['message' => 'Email already registered'], 409);
        }

        $user = new User();
        $user->setEmail($email);
        $user->setPassword($passwordHasher->hashPassword($user, $password));
        $user->setIsVerified(true);
        $user->setRoles(['ROLE_USER']);

        $entityManager->persist($user);
        $entityManager->flush();

        $token = $jwtManager->create($user);

        return $this->json([
            'token' => $token,
            'user'  => [
                'id'    => $user->getId(),
                'email' => $user->getEmail(),
                'type'  => $user->getType(),
                'roles' => $user->getRoles(),
            ]
        ], 201);
    }

    #[Route('/api/google-login', name: 'api_google_login', methods: ['POST'])]
    public function googleLogin(
        Request $request,
        UserRepository $userRepository,
        JWTTokenManagerInterface $jwtManager
    ): JsonResponse {
        $data    = json_decode($request->getContent(), true);
        $idToken = $data['id_token'] ?? null;

        if (!$idToken) {
            return $this->json(['message' => 'ID token is required'], 400);
        }

            $googleResponse = file_get_contents(
            'https://oauth2.googleapis.com/tokeninfo?id_token=' . $idToken
        );
          $googleData = json_decode($googleResponse, true);

        if (!$googleData || isset($googleData['error'])) {
            return $this->json([
                'message' => 'Invalid Google token',
                'debug' => $googleData,
                'token_preview' => substr($idToken, 0, 20),
            ], 401);
        }

        $email = $googleData['email'] ?? null;
        if (!$email) {
            return $this->json(['message' => 'Could not get email from Google'], 401);
        }

        $user = $userRepository->findOneBy(['email' => $email]);
        if (!$user) {
            return $this->json(['message' => 'No account found for this Google email'], 404);
        }

        $token = $jwtManager->create($user);

        return $this->json([
            'token' => $token,
            'user'  => [
                'id'    => $user->getId(),
                'email' => $user->getEmail(),
                'type'  => $user->getType(),
                'roles' => $user->getRoles(),
            ]
        ]);
    }
}