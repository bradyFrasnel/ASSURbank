<?php

namespace App\Entity;

use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Doctrine\Orm\Filter\RangeFilter;
use ApiPlatform\Doctrine\Orm\Filter\DateFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use ApiPlatform\Metadata\Delete;
use App\Repository\TransactionRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: TransactionRepository::class)]
#[ApiFilter(SearchFilter::class, properties: ['type' => 'exact', 'statut' => 'exact', 'compteSource' => 'exact', 'compteDestination' => 'exact'])]
#[ApiFilter(RangeFilter::class, properties: ['montant', 'frais', 'dateTransaction'])]
#[ApiResource(
    operations: [
        new GetCollection(),
        new Get(),
        new Post(),
        new Put(),
        new Delete()
    ],
    normalizationContext: ['groups' => ['transaction:read']],
    denormalizationContext: ['groups' => ['transaction:write']]
)]
class Transaction
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['transaction:read', 'compte:read'])]
    private ?int $id = null;

    #[ORM\Column]
    #[Groups(['transaction:read', 'transaction:write', 'compte:read'])]
    private ?float $montant = null;

    #[ORM\Column(length: 20)]
    #[Groups(['transaction:read', 'transaction:write', 'compte:read'])]
    private ?string $type = null;

    #[ORM\Column(length: 255)]
    #[Groups(['transaction:read', 'transaction:write', 'compte:read'])]
    private ?string $libelle = null;

    #[ORM\Column]
    #[Groups(['transaction:read', 'transaction:write', 'compte:read'])]
    private ?float $frais = null;

    #[ORM\Column]
    #[Groups(['transaction:read', 'compte:read'])]
    private ?\DateTimeImmutable $dateTransaction = null;

    #[ORM\Column(length: 20)]
    #[Groups(['transaction:read', 'transaction:write', 'compte:read'])]
    private ?string $statut = null;

    #[ORM\ManyToOne(inversedBy: 'transactions')]
    #[Groups(['transaction:read', 'transaction:write'])]
    private ?Compte $compteSource = null;

    #[ORM\ManyToOne]
    #[Groups(['transaction:read', 'transaction:write'])]
    private ?Compte $compteDestination = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getMontant(): ?float
    {
        return $this->montant;
    }

    public function setMontant(float $montant): static
    {
        $this->montant = $montant;

        return $this;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getLibelle(): ?string
    {
        return $this->libelle;
    }

    public function setLibelle(string $libelle): static
    {
        $this->libelle = $libelle;

        return $this;
    }

    public function getFrais(): ?float
    {
        return $this->frais;
    }

    public function setFrais(float $frais): static
    {
        $this->frais = $frais;

        return $this;
    }

    public function getDateTransaction(): ?\DateTimeImmutable
    {
        return $this->dateTransaction;
    }

    public function setDateTransaction(\DateTimeImmutable $dateTransaction): static
    {
        $this->dateTransaction = $dateTransaction;

        return $this;
    }

    public function getStatut(): ?string
    {
        return $this->statut;
    }

    public function setStatut(string $statut): static
    {
        $this->statut = $statut;

        return $this;
    }

    public function getCompteSource(): ?Compte
    {
        return $this->compteSource;
    }

    public function setCompteSource(?Compte $compteSource): static
    {
        $this->compteSource = $compteSource;

        return $this;
    }

    public function getCompteDestination(): ?Compte
    {
        return $this->compteDestination;
    }

    public function setCompteDestination(?Compte $compteDestination): static
    {
        $this->compteDestination = $compteDestination;

        return $this;
    }
}
