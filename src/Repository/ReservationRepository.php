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

  
    public function findConflictingReservations(
        Service|int $service,
        \DateTimeImmutable $startAt,
        \DateTimeImmutable $endAt,
        ?int $excludeId = null
    ): array {
        // Création du QueryBuilder (constructeur de requêtes)
        $qb = $this->createQueryBuilder('r');
        
        // Gestion du cas où $service est un int (ID)
    if (is_int($service)) {
        $qb->andWhere('r.service = :serviceId')
           ->setParameter('serviceId', $service);
    } else {
        $qb->andWhere('r.service = :service')
           ->setParameter('service', $service);
    }
        // Construction de la requête SQL
        $qb
                        
            ->andWhere('r.startAt < :endAt')
            ->setParameter('endAt', $endAt)
            ->andWhere('r.endAt > :startAt')
            ->setParameter('startAt', $startAt);

        if ($excludeId !== null) {
            $qb
                ->andWhere('r.id != :excludeId')
                ->setParameter('excludeId', $excludeId);
        }

        // Exécution de la requête et retour des résultats
        return $qb->getQuery()->getResult();
    }

 
    public function findByUserOrderedByDate(int $userId): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.user = :userId')
            ->setParameter('userId', $userId)
            ->orderBy('r.startAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

   
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
