<?php

namespace App\Repository;

use App\Entity\Reservation;
use App\Entity\Service;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Reservation>
 */
class ReservationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Reservation::class);
    }

    /**
     * Trouve les réservations qui se chevauchent avec un créneau donné
     * 
     * Cette méthode est cruciale pour éviter les doubles réservations
     * 
     * @param Service|int $service Le service (objet ou ID)
     * @param \DateTimeImmutable $startAt Début du créneau à vérifier
     * @param \DateTimeImmutable $endAt Fin du créneau à vérifier
     * @param int|null $excludeId ID de réservation à exclure (pour modification)
     * @return Reservation[] Tableau des réservations en conflit
     */
    public function findConflictingReservations(
        Service|int $service,
        \DateTimeImmutable $startAt,
        \DateTimeImmutable $endAt,
        ?int $excludeId = null
    ): array {
        // Création du QueryBuilder (constructeur de requêtes)
        $qb = $this->createQueryBuilder('r');
        
        // Construction de la requête SQL
        $qb
            // WHERE service = :service
            ->andWhere('r.service = :service')
            ->setParameter('service', $service)
            
            ->andWhere('r.startAt < :endAt')
            ->setParameter('endAt', $endAt)
            ->andWhere('r.endAt > :startAt')
            ->setParameter('startAt', $startAt);

        // Si on modifie une réservation existante, on l'exclut de la recherche
        // Sinon elle se détecterait elle-même comme conflit !
        if ($excludeId !== null) {
            $qb
                ->andWhere('r.id != :excludeId')
                ->setParameter('excludeId', $excludeId);
        }

        // Exécution de la requête et retour des résultats
        return $qb->getQuery()->getResult();
    }

    /**
     * Récupère toutes les réservations d'un utilisateur
     * Triées par date décroissante (les plus récentes d'abord)
     * 
     * @param int $userId ID de l'utilisateur
     * @return Reservation[]
     */
    public function findByUserOrderedByDate(int $userId): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.user = :userId')
            ->setParameter('userId', $userId)
            ->orderBy('r.startAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Récupère les réservations futures d'un utilisateur
     * Utile pour afficher "Vos prochaines réservations"
     * 
     * @param int $userId ID de l'utilisateur
     * @return Reservation[]
     */
    public function findUpcomingByUser(int $userId): array
    {
        $now = new \DateTimeImmutable();
        
        return $this->createQueryBuilder('r')
            ->andWhere('r.user = :userId')
            ->setParameter('userId', $userId)
            ->andWhere('r.startAt > :now')
            ->setParameter('now', $now)
            ->orderBy('r.startAt', 'ASC') // Les plus proches d'abord
            ->getQuery()
            ->getResult();
    }

    /**
     * Récupère toutes les réservations d'un service pour une journée donnée
     * Utile pour afficher le calendrier
     * 
     * @param Service $service Le service
     * @param \DateTimeImmutable $date La date
     * @return Reservation[]
     */
    public function findByServiceAndDate(Service $service, \DateTimeImmutable $date): array
    {
        // Début de la journée (00:00:00)
        $startOfDay = $date->setTime(0, 0, 0);
        // Fin de la journée (23:59:59)
        $endOfDay = $date->setTime(23, 59, 59);

        return $this->createQueryBuilder('r')
            ->andWhere('r.service = :service')
            ->setParameter('service', $service)
            ->andWhere('r.startAt >= :startOfDay')
            ->setParameter('startOfDay', $startOfDay)
            ->andWhere('r.startAt <= :endOfDay')
            ->setParameter('endOfDay', $endOfDay)
            ->orderBy('r.startAt', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
