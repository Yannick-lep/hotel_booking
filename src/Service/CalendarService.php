<?php


namespace App\Service;

use App\Entity\Service;
use App\Repository\ReservationRepository;
use DateTimeImmutable;


class CalendarService
{
    /**
     * Configuration des horaires
     */
    private const OPENING_HOUR = 8;
    private const CLOSING_HOUR = 19;
    private const SLOT_DURATION_MINUTES = 30;
    private const MAX_RESERVATION_HOURS = 4;

    public function __construct(
        private ReservationRepository $reservationRepository,
        private ReservationValidator $reservationValidator
    ) {}

    
    public function buildCalendarData(Service $service, DateTimeImmutable $date): array
    {
        // Récupère toutes les réservations existantes pour ce jour
        $existingReservations = $this->reservationRepository->findByServiceAndDate($service, $date);

        // Génère tous les créneaux de 30 minutes
        $allSlots = $this->generateTimeSlots($date);

        // Pour chaque créneau, détermine s'il est disponible ou réservé
        $slotsWithStatus = [];
        foreach ($allSlots as $slotTime) {
            $slotStart = DateTimeImmutable::createFromFormat('Y-m-d H:i', $date->format('Y-m-d') . ' ' . $slotTime);
            
            $slotsWithStatus[] = [
                'time' => $slotTime,
                'datetime' => $slotStart,
                'available' => $this->isSlotAvailable($slotStart, $existingReservations, $date),
                'isPast' => $slotStart < new DateTimeImmutable(),
            ];
        }

        return [
            'date' => $date,
            'service' => $service,
            'slots' => $slotsWithStatus,
            'existingReservations' => $existingReservations,
            'previousDay' => $date->modify('-1 day'),
            'nextDay' => $date->modify('+1 day'),
            'isToday' => $date->format('Y-m-d') === (new DateTimeImmutable())->format('Y-m-d'),
        ];
    }

  
    private function generateTimeSlots(DateTimeImmutable $date): array
    {
        $slots = [];
        
        // Commence à 08:00
        $current = $date->setTime(self::OPENING_HOUR, 0);
        // Termine à 19:00 (mais on n'inclut pas 19:00 comme début de créneau)
        $end = $date->setTime(self::CLOSING_HOUR, 0);

        while ($current < $end) {
            $slots[] = $current->format('H:i');
            $current = $current->modify('+' . self::SLOT_DURATION_MINUTES . ' minutes');
        }

        return $slots;
    }

    
    private function isSlotAvailable(
        DateTimeImmutable $slotStart,
        array $existingReservations,
        DateTimeImmutable $date
    ): bool {
        // Si le créneau est dans le passé, indisponible
        if ($slotStart < new DateTimeImmutable()) {
            return false;
        }

        // Si ce n'est pas un jour ouvré, indisponible
        $dayOfWeek = (int) $date->format('N');
        if ($dayOfWeek === 7) { // Dimanche
            return false;
        }

        // Fin du créneau de 30 minutes
        $slotEnd = $slotStart->modify('+' . self::SLOT_DURATION_MINUTES . ' minutes');

        // Vérifie qu'aucune réservation ne chevauche ce créneau
        foreach ($existingReservations as $reservation) {
            // Logique de chevauchement :
            // Le créneau chevauche une réservation si :
            // - Il commence avant que la réservation se termine
            // - ET il se termine après que la réservation ait commencé
            if ($slotStart < $reservation->getEndAt() && $slotEnd > $reservation->getStartAt()) {
                return false; // Chevauchement détecté
            }
        }

        return true; // Aucun conflit
    }

    
    public function getAvailableDurations(Service $service, DateTimeImmutable $startTime): array
    {
        $durations = [];
        $maxSlots = (self::MAX_RESERVATION_HOURS * 60) / self::SLOT_DURATION_MINUTES; // 8 créneaux max (4h)
        
        $currentEnd = $startTime;
        
        // On teste chaque durée possible (30min, 1h, 1h30, etc.)
        for ($i = 1; $i <= $maxSlots; $i++) {
            $currentEnd = $startTime->modify('+' . ($i * self::SLOT_DURATION_MINUTES) . ' minutes');
            
            // Vérifie que l'heure de fin ne dépasse pas 19:00
            $endHour = (int) $currentEnd->format('H');
            $endMinute = (int) $currentEnd->format('i');
            if ($endHour > self::CLOSING_HOUR || ($endHour === self::CLOSING_HOUR && $endMinute > 0)) {
                break; // On ne peut pas aller au-delà de 19:00
            }

            // Vérifie qu'il n'y a pas de réservation entre startTime et currentEnd
            $conflicts = $this->reservationRepository->findConflictingReservations(
                $service,
                $startTime,
                $currentEnd,
                null
            );

            if (count($conflicts) === 0) {
                // Calcul de la durée en minutes
                $durationMinutes = $i * self::SLOT_DURATION_MINUTES;
                
                // Formatage lisible : "30 minutes", "1 heure", "1h30", etc.
                $durationLabel = $this->formatDuration($durationMinutes);
                
                $durations[] = [
                    'end' => $currentEnd,
                    'minutes' => $durationMinutes,
                    'label' => $durationLabel,
                ];
            } else {
                // Conflit détecté, on arrête
                break;
            }
        }

        return $durations;
    }

  
    private function formatDuration(int $minutes): string
    {
        $hours = intdiv($minutes, 60);
        $mins = $minutes % 60;

        if ($hours === 0) {
            return $minutes . ' minutes';
        }

        if ($mins === 0) {
            return $hours . ($hours === 1 ? ' heure' : ' heures');
        }

        return $hours . 'h' . str_pad((string)$mins, 2, '0', STR_PAD_LEFT);
    }

  
    public function isOpenDay(DateTimeImmutable $date): bool
    {
        $dayOfWeek = (int) $date->format('N');
        return $dayOfWeek >= 1 && $dayOfWeek <= 6; // 1=lundi, 6=samedi
    }

   
    public function getClosedMessage(DateTimeImmutable $date): ?string
    {
        if (!$this->isOpenDay($date)) {
            return 'Nos services sont fermés le dimanche.';
        }

        return null;
    }
}


