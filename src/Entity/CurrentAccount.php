<?php

namespace App\Entity;

use App\Repository\CurrentAccountRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: CurrentAccountRepository::class)]
class CurrentAccount {
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(["getBooklet", "getCurrentAccount"])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Groups(["getBooklet", "getCurrentAccount"])]
    #[Assert\NotNull(message: "Un compte courrant doit avoir un nom.")]
    #[Assert\Length(min: 2, max: 255,
                    minMessage: "Le nom du compte courrant doit comporter au moins {{ limit }} caractères.",
                    maxMessage: "Le nom du compte courrant doit contenir maximum {{ limit }} caractères.")]
    private ?string $name = null;

    #[ORM\Column]
    #[Groups(["getBooklet", "getCurrentAccount"])]
    #[Assert\NotNull(message: "Un compte courrant doit avoir de l'argent.")]
    #[Assert\PositiveOrZero(message: "Un compte courrant doit contenir un nombre d'argent positif (ou égal à zéro).")]
    private ?float $money = null;

    #[ORM\Column]
    #[Assert\NotNull(message: "Un compte courrant doit avoir un statut.")]
    private ?bool $status = null;

    #[ORM\OneToMany(mappedBy: 'currentAccount', targetEntity: Booklet::class)]
    #[Groups(["getCurrentAccount"])]
    private Collection $booklets;

    public function __construct() {
        $this->booklets = new ArrayCollection();
    }

    public function getId(): ?int {
        return $this->id;
    }

    public function getName(): ?string {
        return $this->name;
    }

    public function setName(string $name): self {
        $this->name = $name;

        return $this;
    }

    public function getMoney(): ?float {
        return $this->money;
    }

    public function setMoney(float $money): self {
        $this->money = $money;

        return $this;
    }

    public function isStatus(): ?bool {
        return $this->status;
    }

    public function setStatus(bool $status): self {
        $this->status = $status;

        return $this;
    }

    /**
     * @return Collection<int, Booklet>
     */
    public function getBooklets(): Collection {
        return $this->booklets;
    }

    public function addBooklet(Booklet $booklet): self {
        if (!$this->booklets->contains($booklet)) {
            $this->booklets->add($booklet);
            $booklet->setCurrentAccount($this);
        }

        return $this;
    }

    public function removeBooklet(Booklet $booklet): self {
        if ($this->booklets->removeElement($booklet)) {
            // set the owning side to null (unless already changed)
            if ($booklet->getCurrentAccount() === $this) {
                $booklet->setCurrentAccount(null);
            }
        }

        return $this;
    }
}
