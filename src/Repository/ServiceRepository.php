<?php


namespace App\Repository;

use App\Entity\Service;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Service>
 */
class ServiceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Service::class);
    }

    /**
     * Récupère tous les services triés par nom
     * 
     * @return Service[]
     */
    public function findAllOrdered(): array
    {
        return $this->createQueryBuilder('s')
            ->orderBy('s.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve un service par son slug (nom formaté pour URL)
     * 
     * @param string $slug Le slug (ex: "spa", "salle-de-sport")
     * @return Service|null
     */
    public function findOneBySlug(string $slug): ?Service
    {
       
        $services = $this->findAll();
        
        foreach ($services as $service) {
            if ($service->getSlug() === $slug) {
                return $service;
            }
        }
        
        return null;
    }
}