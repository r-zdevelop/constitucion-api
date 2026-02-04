<?php

declare(strict_types=1);

namespace App\Presentation\API\Controller;

use App\Application\UseCase\Auth\LoginUserUseCase;
use App\Application\UseCase\Auth\RegisterUserUseCase;
use App\Domain\Exception\DuplicateEmailException;
use App\Domain\Exception\InvalidCredentialsException;
use App\Domain\Exception\UserNotFoundException;
use App\Presentation\API\Request\LoginRequest;
use App\Presentation\API\Request\RegisterRequest;
use App\Presentation\API\Response\UserResponse;
use InvalidArgumentException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/v1/auth', name: 'api_auth_')]
final class AuthController extends AbstractController
{
    public function __construct(
        private readonly ValidatorInterface $validator,
        private readonly RegisterUserUseCase $registerUseCase,
        private readonly LoginUserUseCase $loginUseCase
    ) {
    }

    #[Route('/register', name: 'register', methods: ['POST'])]
    public function register(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];

        $registerRequest = new RegisterRequest(
            email: $data['email'] ?? '',
            password: $data['password'] ?? '',
            name: $data['name'] ?? ''
        );

        $violations = $this->validator->validate($registerRequest);

        if (count($violations) > 0) {
            $errors = [];
            foreach ($violations as $violation) {
                $errors[$violation->getPropertyPath()] = $violation->getMessage();
            }

            return $this->json([
                'type' => 'https://tools.ietf.org/html/rfc7807',
                'title' => 'Validation Error',
                'status' => Response::HTTP_BAD_REQUEST,
                'errors' => $errors,
            ], Response::HTTP_BAD_REQUEST);
        }

        try {
            $result = $this->registerUseCase->execute(
                $registerRequest->email,
                $registerRequest->password,
                $registerRequest->name
            );

            return $this->json([
                'user' => (new UserResponse($result['user']))->toArray(),
                'token' => $result['token'],
            ], Response::HTTP_CREATED);
        } catch (DuplicateEmailException $e) {
            return $this->json([
                'type' => 'https://tools.ietf.org/html/rfc7807',
                'title' => 'Conflict',
                'status' => Response::HTTP_CONFLICT,
                'detail' => $e->getMessage(),
            ], Response::HTTP_CONFLICT);
        } catch (InvalidArgumentException $e) {
            return $this->json([
                'type' => 'https://tools.ietf.org/html/rfc7807',
                'title' => 'Bad Request',
                'status' => Response::HTTP_BAD_REQUEST,
                'detail' => $e->getMessage(),
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    #[Route('/login', name: 'login', methods: ['POST'])]
    public function login(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];

        $loginRequest = new LoginRequest(
            email: $data['email'] ?? '',
            password: $data['password'] ?? ''
        );

        $violations = $this->validator->validate($loginRequest);

        if (count($violations) > 0) {
            $errors = [];
            foreach ($violations as $violation) {
                $errors[$violation->getPropertyPath()] = $violation->getMessage();
            }

            return $this->json([
                'type' => 'https://tools.ietf.org/html/rfc7807',
                'title' => 'Validation Error',
                'status' => Response::HTTP_BAD_REQUEST,
                'errors' => $errors,
            ], Response::HTTP_BAD_REQUEST);
        }

        try {
            $result = $this->loginUseCase->execute(
                $loginRequest->email,
                $loginRequest->password
            );

            return $this->json([
                'user' => (new UserResponse($result['user']))->toArray(),
                'token' => $result['token'],
            ], Response::HTTP_OK);
        } catch (UserNotFoundException|InvalidCredentialsException) {
            return $this->json([
                'type' => 'https://tools.ietf.org/html/rfc7807',
                'title' => 'Unauthorized',
                'status' => Response::HTTP_UNAUTHORIZED,
                'detail' => 'Invalid credentials.',
            ], Response::HTTP_UNAUTHORIZED);
        }
    }
}
