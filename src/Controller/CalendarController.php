<?php


namespace App\Controller;

use App\Entity\Service;
use App\Repository\ServiceRepository;
use App\Service\CalendarService;
use DateTimeImmutable;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Contrôleur gérant l'affichage du calendrier
 */
class CalendarController extends AbstractController
{
    
    #[Route('/calendar/{serviceSlug}/{date}', name: 'calendar_show', requirements: ['date' => '\d{4}-\d{2}-\d{2}'])]
    public function show(
        string $serviceSlug,
        string $date,
        ServiceRepository $serviceRepository,
        CalendarService $calendarService
    ): Response {
        // 1. RÉCUPÉRATION DU SERVICE
        // On cherche le service par son slug
        $service = $serviceRepository->findOneBySlug($serviceSlug);
        
        if (!$service) {
            // Si le service n'existe pas, erreur 404
            throw $this->createNotFoundException('Service non trouvé');
        }

        // 2. PARSING ET VALIDATION DE LA DATE
        try {
            // Convertit la chaîne "2026-02-14" en objet DateTimeImmutable
            $dateObj = DateTimeImmutable::createFromFormat('Y-m-d', $date);
            
            if (!$dateObj) {
                throw new \Exception('Format de date invalide');
            }
            
            // Réinitialise l'heure à 00:00:00 pour éviter les problèmes
            $dateObj = $dateObj->setTime(0, 0, 0);
            
        } catch (\Exception $e) {
            // Si la date est invalide, on redirige vers aujourd'hui
            $this->addFlash('error', 'Date invalide. Redirection vers aujourd\'hui.');
            return $this->redirectToRoute('calendar_show', [
                'serviceSlug' => $serviceSlug,
                'date' => (new DateTimeImmutable())->format('Y-m-d'),
            ]);
        }

        // 3. CONSTRUCTION DES DONNÉES DU CALENDRIER
        // Le service CalendarService fait tout le travail
        $calendarData = $calendarService->buildCalendarData($service, $dateObj);

        // 4. VÉRIFICATION SI LE JOUR EST FERMÉ
        $closedMessage = $calendarService->getClosedMessage($dateObj);

        // 5. RENDU DU TEMPLATE
        return $this->render('calendar/show.html.twig', [
            'calendar' => $calendarData,
            'closedMessage' => $closedMessage,
        ]);
    }

   
    #[Route('/calendar/{serviceSlug}', name: 'calendar_today')]
    public function today(string $serviceSlug): Response
    {
        // Redirige vers la date d'aujourd'hui
        return $this->redirectToRoute('calendar_show', [
            'serviceSlug' => $serviceSlug,
            'date' => (new DateTimeImmutable())->format('Y-m-d'),
        ]);
    }
}
