<?php

declare(strict_types=1);

namespace App\Presentation\API\Response;

use App\Domain\Document\User;

final readonly class UserResponse
{
    public string $id;
    public string $email;
    public string $name;
    public string $role;
    public string $createdAt;

    public function __construct(User $user)
    {
        $this->id = $user->getId() ?? '';
        $this->email = $user->getEmail();
        $this->name = $user->getName();
        $this->role = $user->getRole()->value;
        $this->createdAt = $user->getCreatedAt()->format(\DateTimeInterface::ATOM);
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'email' => $this->email,
            'name' => $this->name,
            'role' => $this->role,
            'createdAt' => $this->createdAt,
        ];
    }
}
