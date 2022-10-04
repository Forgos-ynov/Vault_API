<?php

namespace App\Entity;

use App\Repository\CurrentAccountRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: CurrentAccountRepository::class)]
class CurrentAccount {
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(["getAllBooklets", "getBooklet"])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Groups(["getAllBooklet", "getBooklet"])]
    private ?string $name = null;

    #[ORM\Column]
    #[Groups(["getAllBooklet", "getBooklet"])]
    private ?float $money = null;

    #[ORM\Column]
    private ?bool $status = null;

    #[ORM\OneToMany(mappedBy: 'currentAccount', targetEntity: Booklet::class)]
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
