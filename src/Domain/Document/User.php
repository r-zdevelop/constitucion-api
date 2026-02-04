<?php

declare(strict_types=1);

namespace App\Domain\Document;

use App\Domain\ValueObject\Email;
use App\Domain\ValueObject\Role;
use Doctrine\ODM\MongoDB\Mapping\Annotations as ODM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use DateTimeImmutable;

#[ODM\Document(collection: 'users')]
#[ODM\Index(keys: ['email' => 1], options: ['unique' => true])]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ODM\Id]
    private ?string $id = null;

    #[ODM\Field(type: 'string')]
    private string $email;

    #[ODM\Field(type: 'string')]
    private string $password;

    #[ODM\Field(type: 'string')]
    private string $name;

    #[ODM\Field(type: 'string')]
    private string $role;

    #[ODM\Field(type: 'date_immutable')]
    private DateTimeImmutable $createdAt;

    #[ODM\Field(type: 'date_immutable')]
    private DateTimeImmutable $updatedAt;

    #[ODM\Field(type: 'date_immutable', nullable: true)]
    private ?DateTimeImmutable $lastLoginAt = null;

    public function __construct(
        Email $email,
        string $hashedPassword,
        string $name,
        Role $role = null
    ) {
        $this->email = $email->getValue();
        $this->password = $hashedPassword;
        $this->name = $name;
        $this->role = ($role ?? Role::default())->value;
        $this->createdAt = new DateTimeImmutable();
        $this->updatedAt = new DateTimeImmutable();
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getEmailVO(): Email
    {
        return new Email($this->email);
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getRole(): Role
    {
        return Role::from($this->role);
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function getLastLoginAt(): ?DateTimeImmutable
    {
        return $this->lastLoginAt;
    }

    public function setPassword(string $hashedPassword): void
    {
        $this->password = $hashedPassword;
        $this->updatedAt = new DateTimeImmutable();
    }

    public function setName(string $name): void
    {
        $this->name = $name;
        $this->updatedAt = new DateTimeImmutable();
    }

    public function setRole(Role $role): void
    {
        $this->role = $role->value;
        $this->updatedAt = new DateTimeImmutable();
    }

    public function recordLogin(): void
    {
        $this->lastLoginAt = new DateTimeImmutable();
    }

    // UserInterface implementation

    public function getRoles(): array
    {
        return [$this->role, 'ROLE_USER'];
    }

    public function eraseCredentials(): void
    {
        // No temporary sensitive data to erase
    }

    public function getUserIdentifier(): string
    {
        return $this->email;
    }
}
