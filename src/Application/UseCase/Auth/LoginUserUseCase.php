<?php

declare(strict_types=1);

namespace App\Application\UseCase\Auth;

use App\Domain\Document\User;
use App\Domain\Exception\InvalidCredentialsException;
use App\Domain\Exception\UserNotFoundException;
use App\Domain\Repository\UserRepositoryInterface;
use App\Infrastructure\Security\JwtTokenManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final readonly class LoginUserUseCase
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private UserPasswordHasherInterface $passwordHasher,
        private JwtTokenManager $jwtTokenManager
    ) {
    }

    /**
     * @return array{user: User, token: string}
     * @throws UserNotFoundException|InvalidCredentialsException
     */
    public function execute(string $email, string $password): array
    {
        $user = $this->userRepository->findByEmail($email);

        if ($user === null) {
            throw UserNotFoundException::withEmail($email);
        }

        if (!$this->passwordHasher->isPasswordValid($user, $password)) {
            throw InvalidCredentialsException::create();
        }

        $user->recordLogin();
        $this->userRepository->save($user);
        $this->userRepository->flush();

        $token = $this->jwtTokenManager->createToken($user);

        return [
            'user' => $user,
            'token' => $token,
        ];
    }
}
