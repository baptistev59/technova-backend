<?php
namespace App\Entity\Traits;

use Doctrine\ORM\Mapping as ORM;

/**
 * Cette classe ajoute des propriétés de temps (createdAt et updatedAt) à une entité.
 * Elle est utilisée pour enregistrer les dates et heures de création et de dernière mise à jour de l'entité.
 *
 * Les propriétés de temps sont définies comme des colonnes de base de données avec le type de données DateTimeImmutable.
 * Les méthodes onPrePersist et onPreUpdate mettent à jour les propriétés de temps avant l'exécution de l'opération d'insertion ou de mise à jour dans la base de données.
 *
 * Cette classe est utilisée pour faciliter la gestion des dates et heures de création et de dernière mise à jour des entités dans votre application.
 */
trait Timestampable
{
    // Définition des propriétés de temps
    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $updatedAt = null;

    // Méthodes pour mettre à jour les propriétés de temps
    /**
     * Cette méthode est appelée avant l'exécution de l'opération d'insertion dans la base de données.
     * Elle définit les propriétés createdAt et updatedAt avec la date et l'heure actuelles.
     */
    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $now = new \DateTimeImmutable();
        $this->createdAt = $now;
        $this->updatedAt = $now;
    }

    /**
     * Cette méthode est appelée avant l'exécution de l'opération de mise à jour dans la base de données.
     * Elle met à jour uniquement la propriété updatedAt avec la date et l'heure actuelles.
     */
    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
