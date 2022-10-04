<?php

namespace App\Entity;

use App\Repository\BookletRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;


#[ORM\Entity(repositoryClass: BookletRepository::class)]
class Booklet {
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(["getAllBooklet", "getBooklet"])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Groups(["getAllBooklet", "getBooklet"])]
    private ?string $name = null;

    #[ORM\Column]
    #[Groups(["getAllBooklet", "getBooklet"])]
    #[Assert\NotNull(message: "Un livret doit avoir de l'argent.")]
    private ?float $money = null;

    #[ORM\ManyToOne(inversedBy: 'booklets')]
    #[Groups(["getAllBooklet", "getBooklet"])]
    private ?BookletPercent $bookletPercent = null;

    #[ORM\ManyToOne(inversedBy: 'booklets')]
    #[Groups(["getAllBooklet", "getBooklet"])]
    private ?CurrentAccount $currentAccount = null;

    #[ORM\Column]
    private ?bool $status = null;

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

    public function getBookletPercent(): ?BookletPercent {
        return $this->bookletPercent;
    }

    public function setBookletPercent(?BookletPercent $bookletPercent): self {
        $this->bookletPercent = $bookletPercent;

        return $this;
    }

    public function getCurrentAccount(): ?CurrentAccount {
        return $this->currentAccount;
    }

    public function setCurrentAccount(?CurrentAccount $currentAccount): self {
        $this->currentAccount = $currentAccount;

        return $this;
    }

    public function isStatus(): ?bool {
        return $this->status;
    }

    public function setStatus(bool $status): self {
        $this->status = $status;

        return $this;
    }
}
