<?php

declare(strict_types=1);

namespace App\Application\UseCase\Auth;

use App\Domain\Document\User;
use App\Domain\Exception\DuplicateEmailException;
use App\Domain\Repository\UserRepositoryInterface;
use App\Domain\ValueObject\Email;
use App\Domain\ValueObject\Role;
use App\Infrastructure\Security\JwtTokenManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final readonly class RegisterUserUseCase
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private UserPasswordHasherInterface $passwordHasher,
        private JwtTokenManager $jwtTokenManager
    ) {
    }

    /**
     * @return array{user: User, token: string}
     * @throws DuplicateEmailException
     */
    public function execute(string $email, string $password, string $name): array
    {
        $emailVO = new Email($email);

        if ($this->userRepository->existsByEmail($emailVO->getValue())) {
            throw DuplicateEmailException::create($emailVO->getValue());
        }

        // Create user with temporary password (will be hashed)
        $user = new User(
            $emailVO,
            '', // temporary, will be set after hashing
            $name,
            Role::default()
        );

        // Hash password using the user instance
        $hashedPassword = $this->passwordHasher->hashPassword($user, $password);
        $user->setPassword($hashedPassword);

        $this->userRepository->save($user);
        $this->userRepository->flush();

        $token = $this->jwtTokenManager->createToken($user);

        return [
            'user' => $user,
            'token' => $token,
        ];
    }
}
