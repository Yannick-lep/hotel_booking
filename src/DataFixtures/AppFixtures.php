<?php


namespace App\DataFixtures;

use App\Entity\Service;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Cette classe va créer des données de test dans la base
 * Très utile pour le développement
 */
class AppFixtures extends Fixture
{
    public function __construct(
        private UserPasswordHasherInterface $passwordHasher
    ) {}

    public function load(ObjectManager $manager): void
    {
        // ===== CRÉATION DES SERVICES =====
        
        $spaService = new Service();
        $spaService->setName('Spa');
        $spaService->setDescription('Profitez de notre espace spa avec jacuzzi, sauna et hammam.');
        $manager->persist($spaService);

        $massageService = new Service();
        $massageService->setName('Massage');
        $massageService->setDescription('Massage relaxant par des professionnels certifiés.');
        $manager->persist($massageService);

        $gymService = new Service();
        $gymService->setName('Salle de sport');
        $gymService->setDescription('Salle de sport équipée avec machines cardio et musculation.');
        $manager->persist($gymService);

        $loungeService = new Service();
        $loungeService->setName('Espace détente privatif');
        $loungeService->setDescription('Espace détente privé avec vue panoramique.');
        $manager->persist($loungeService);

        // ===== CRÉATION D'UTILISATEURS DE TEST =====
        
        // Utilisateur de test 1
        $user1 = new User();
        $user1->setEmail('user@example.com');
        $user1->setPassword($this->passwordHasher->hashPassword($user1, 'password'));
        $user1->setRoles(['ROLE_USER']);
        $manager->persist($user1);

        // Utilisateur de test 2
        $user2 = new User();
        $user2->setEmail('john@example.com');
        $user2->setPassword($this->passwordHasher->hashPassword($user2, 'password'));
        $user2->setRoles(['ROLE_USER']);
        $manager->persist($user2);

        // Admin (pour plus tard si nécessaire)
        $admin = new User();
        $admin->setEmail('admin@example.com');
        $admin->setPassword($this->passwordHasher->hashPassword($admin, 'admin'));
        $admin->setRoles(['ROLE_ADMIN']);
        $manager->persist($admin);

        // Sauvegarde en base
        $manager->flush();

        // Message de confirmation dans la console
        echo " Services créés : Spa, Massage, Salle de sport, Espace détente\n";
        echo " Utilisateurs créés :\n";
        echo "   - user@example.com (password: password)\n";
        echo "   - john@example.com (password: password)\n";
        echo "   - admin@example.com (password: admin)\n";
    }
}
