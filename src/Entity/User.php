<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use JMS\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: UserRepository::class)]
class User implements UserInterface, PasswordAuthenticatedUserInterface {
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(["getBooklet", "getCurrentAccount"])]
    private ?int $id = null;

    #[ORM\Column(length: 180, unique: true)]
    #[Assert\NotNull(message: "Un utilisateur doit avoir un nom.")]
    #[Assert\Length(min: 2, max: 180, minMessage: "Le nom d'un utilisateur doit contenir au moins {{ limit }} caractères",
                    maxMessage: "Le nom d'un utilisateur doit contenir maximum {{ limit }} caractères")]
    #[Groups(["getBooklet", "getCurrentAccount"])]
    private ?string $username = null;

    #[ORM\Column]
    #[Assert\NotNull(message: "Un utilisateur doit avoir une liste de rôle(s).")]
    private array $roles = [];

    /**
     * @var string The hashed password
     */
    #[ORM\Column]

    #[Assert\NotNull(message: "Un utilisateur doit avoir un mot de passe.")]
    private ?string $password = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Assert\NotNull(message: "Un utilisateur doit avoir une date de création.")]
    #[Assert\DateTime(format: "Y-m-d H:i:s")]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\ManyToOne(inversedBy: 'users')]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull(message: "Un utilisateur doit avoir un compte courrant.")]
    private ?CurrentAccount $currentAccount = null;

    public function getId(): ?int {
        return $this->id;
    }

    public function getUsername(): ?string {
        return $this->username;
    }

    public function setUsername(string $username): self {
        $this->username = $username;

        return $this;
    }

    /**
     * A visual identifier that represents this user.
     *
     * @see UserInterface
     */
    public function getUserIdentifier(): string {
        return (string)$this->username;
    }

    /**
     * @see UserInterface
     */
    public function getRoles(): array {
        $roles = $this->roles;
        // guarantee every user at least has ROLE_USER
        $roles[] = 'ROLE_USER';

        return array_unique($roles);
    }

    public function setRoles(array $roles): self {
        $this->roles = $roles;

        return $this;
    }

    /**
     * @see PasswordAuthenticatedUserInterface
     */
    public function getPassword(): string {
        return $this->password;
    }

    public function setPassword(string $password): self {
        $this->password = $password;

        return $this;
    }

    /**
     * @see UserInterface
     */
    public function eraseCredentials() {
        // If you store any temporary, sensitive data on the user, clear it here
        // $this->plainPassword = null;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): self
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getCurrentAccount(): ?CurrentAccount
    {
        return $this->currentAccount;
    }

    public function setCurrentAccount(?CurrentAccount $currentAccount): self
    {
        $this->currentAccount = $currentAccount;

        return $this;
    }
}
