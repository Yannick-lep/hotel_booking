<?php

namespace App\Controller;

use App\Entity\Reservation;
use App\Form\ReservationType;
use App\Repository\ReservationRepository;
use App\Repository\ServiceRepository;
use App\Service\CalendarService;
use App\Service\ReservationValidator;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Contrôleur gérant le CRUD des réservations
 */
#[Route('/reservation')]
class ReservationController extends AbstractController
{
   
    #[Route('/new/{serviceSlug}/{startTime}', name: 'reservation_new')]
    #[IsGranted('ROLE_USER')] 
    public function new(
        string $serviceSlug,
        string $startTime,
        Request $request,
        ServiceRepository $serviceRepository,
        CalendarService $calendarService,
        ReservationValidator $validator,
        EntityManagerInterface $em
    ): Response {
        // ===== 1. RÉCUPÉRATION DU SERVICE =====
        $service = $serviceRepository->findOneBySlug($serviceSlug);
        
        if (!$service) {
            throw $this->createNotFoundException('Service non trouvé');
        }

        try {
            $startAt = DateTimeImmutable::createFromFormat('Y-m-d H:i', $startTime);
            
            if (!$startAt) {
                throw new \Exception('Format de date invalide');
            }
        } catch (\Exception $e) {
            $this->addFlash('error', 'Heure de début invalide.');
            return $this->redirectToRoute('calendar_today', ['serviceSlug' => $serviceSlug]);
        }

        
        $availableDurations = $calendarService->getAvailableDurations($service, $startAt);

        // Si aucune durée n'est disponible, le créneau est déjà réservé
        if (empty($availableDurations)) {
            $this->addFlash('error', 'Ce créneau n\'est plus disponible.');
            return $this->redirectToRoute('calendar_show', [
                'serviceSlug' => $serviceSlug,
                'date' => $startAt->format('Y-m-d')
            ]);
        }

        
        $reservation = new Reservation();
        $reservation->setUser($this->getUser()); 
        $reservation->setService($service);
        $reservation->setStartAt($startAt);

        
        $form = $this->createForm(ReservationType::class, $reservation, [
            'available_durations' => $availableDurations,
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // La date de fin vient du formulaire (sous forme de string)
            // On doit la convertir en DateTimeImmutable
            $endAtString = $form->get('endAt')->getData();
            $endAt = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $endAtString);
            
            $reservation->setEndAt($endAt);

            // On utilise notre service ReservationValidator
            $errors = $validator->validate($reservation);

            if (empty($errors)) {
                $em->persist($reservation);
                $em->flush();

                $this->addFlash('success', sprintf(
                    'Votre réservation pour le %s de %s à %s a été confirmée !',
                    $service->getName(),
                    $startAt->format('d/m/Y à H:i'),
                    $endAt->format('H:i')
                ));

                return $this->redirectToRoute('reservation_list');
            } else {
                foreach ($errors as $error) {
                    $this->addFlash('error', $error);
                }
            }
        }

        return $this->render('reservation/new.html.twig', [
            'form' => $form,
            'service' => $service,
            'startAt' => $startAt,
            'availableDurations' => $availableDurations,
        ]);
    }

   
    #[Route('/mes-reservations', name: 'reservation_list')]
    #[IsGranted('ROLE_USER')]
    public function list(ReservationRepository $reservationRepository): Response
    {
        // Récupère l'utilisateur connecté
        $user = $this->getUser();

        // Récupère toutes ses réservations, triées par date
        $reservations = $reservationRepository->findByUserOrderedByDate($user->getId());

        // Séparation des réservations futures et passées
        $now = new DateTimeImmutable();
        $upcomingReservations = [];
        $pastReservations = [];

        foreach ($reservations as $reservation) {
            if ($reservation->getStartAt() > $now) {
                $upcomingReservations[] = $reservation;
            } else {
                $pastReservations[] = $reservation;
            }
        }

        return $this->render('reservation/list.html.twig', [
            'upcomingReservations' => $upcomingReservations,
            'pastReservations' => $pastReservations,
        ]);
    }

  
    #[Route('/{id}/edit', name: 'reservation_edit')]
    #[IsGranted('ROLE_USER')]
    public function edit(
        Reservation $reservation,
        Request $request,
        CalendarService $calendarService,
        ReservationValidator $validator,
        EntityManagerInterface $em
    ): Response {
        // ===== 1. VÉRIFICATION DE PROPRIÉTÉ =====
        // Seul le propriétaire peut modifier sa réservation
        if ($reservation->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException('Vous ne pouvez pas modifier cette réservation.');
        }

        // ===== 2. VÉRIFICATION QUE CE N'EST PAS DANS LE PASSÉ =====
        if ($reservation->getStartAt() < new DateTimeImmutable()) {
            $this->addFlash('error', 'Vous ne pouvez pas modifier une réservation passée.');
            return $this->redirectToRoute('reservation_list');
        }

        // ===== 3. CALCUL DES DURÉES DISPONIBLES =====
        $availableDurations = $calendarService->getAvailableDurations(
            $reservation->getService(),
            $reservation->getStartAt()
        );

        if (empty($availableDurations)) {
            $this->addFlash('error', 'Aucune durée disponible pour cette modification.');
            return $this->redirectToRoute('reservation_list');
        }

        // ===== 4. CRÉATION DU FORMULAIRE =====
        $form = $this->createForm(ReservationType::class, $reservation, [
            'available_durations' => $availableDurations,
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Conversion de la nouvelle date de fin
            $endAtString = $form->get('endAt')->getData();
            $endAt = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $endAtString);
            
            $reservation->setEndAt($endAt);

            // ===== 5. VALIDATION MÉTIER =====
            // On passe la réservation existante pour l'exclure de la détection de conflit
            $errors = $validator->validate($reservation, $reservation);

            if (empty($errors)) {
                $em->flush();

                $this->addFlash('success', 'Votre réservation a été modifiée avec succès.');
                return $this->redirectToRoute('reservation_list');
            } else {
                foreach ($errors as $error) {
                    $this->addFlash('error', $error);
                }
            }
        }

        return $this->render('reservation/edit.html.twig', [
            'form' => $form,
            'reservation' => $reservation,
            'availableDurations' => $availableDurations,
        ]);
    }


    #[Route('/{id}/delete', name: 'reservation_delete', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function delete(
        Reservation $reservation,
        Request $request,
        EntityManagerInterface $em
    ): Response {
        // ===== 1. VÉRIFICATION DE PROPRIÉTÉ =====
        if ($reservation->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException('Vous ne pouvez pas supprimer cette réservation.');
        }

        // ===== 2. VÉRIFICATION DU TOKEN CSRF =====
        // Protection contre les attaques CSRF
        $submittedToken = $request->request->get('_token');
        
        if (!$this->isCsrfTokenValid('delete' . $reservation->getId(), $submittedToken)) {
            $this->addFlash('error', 'Token de sécurité invalide.');
            return $this->redirectToRoute('reservation_list');
        }

        // ===== 3. SUPPRESSION =====
        $serviceName = $reservation->getService()->getName();
        $startTime = $reservation->getStartAt()->format('d/m/Y à H:i');
        
        $em->remove($reservation);
        $em->flush();

        $this->addFlash('success', sprintf(
            'Votre réservation du %s pour le %s a été annulée.',
            $serviceName,
            $startTime
        ));

        return $this->redirectToRoute('reservation_list');
    }


    #[Route('/{id}', name: 'reservation_show')]
    #[IsGranted('ROLE_USER')]
    public function show(Reservation $reservation): Response
    {
        // Vérification de propriété
        if ($reservation->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException('Vous ne pouvez pas voir cette réservation.');
        }

        return $this->render('reservation/show.html.twig', [
            'reservation' => $reservation,
        ]);
    }
}
