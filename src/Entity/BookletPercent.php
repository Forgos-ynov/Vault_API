<?php

namespace App\Entity;

use App\Repository\BookletPercentRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: BookletPercentRepository::class)]
class BookletPercent {
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(["getBooklet", "getCurrentAccount"])]
    private ?int $id = null;

    #[ORM\Column]
    #[Groups(["getBooklet", "getCurrentAccount"])]
    #[Assert\NotNull(message: "Un pourcentage de livret doit contenir un pourcentage.")]
    private ?float $percent = null;

    #[ORM\OneToMany(mappedBy: 'bookletAccount', targetEntity: Booklet::class)]
    private Collection $booklets;

    public function __construct() {
        $this->booklets = new ArrayCollection();
    }

    public function getId(): ?int {
        return $this->id;
    }

    public function getPercent(): ?float {
        return $this->percent;
    }

    public function setPercent(float $percent): self {
        $this->percent = $percent;

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
            $booklet->setBookletPercent($this);
        }

        return $this;
    }

    public function removeBooklet(Booklet $booklet): self {
        if ($this->booklets->removeElement($booklet)) {
            // set the owning side to null (unless already changed)
            if ($booklet->getBookletPercent() === $this) {
                $booklet->setBookletPercent(null);
            }
        }

        return $this;
    }
}
