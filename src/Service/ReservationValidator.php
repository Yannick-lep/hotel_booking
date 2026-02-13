<?php
// src/Service/ReservationValidator.php

namespace App\Service;

use App\Entity\Reservation;
use App\Repository\ReservationRepository;
use DateTimeImmutable;

/**
 * Service centralisé pour valider les règles métier des réservations
 * 
 * Ce service vérifie :
 * 1. Que la fin est après le début
 * 2. Que la durée ne dépasse pas 4h
 * 3. Qu'on ne réserve pas dans le passé
 * 4. Qu'il n'y a pas de conflit avec d'autres réservations
 * 5. Que le créneau est dans les horaires d'ouverture
 */
class ReservationValidator
{
    /**
     * Constantes pour les règles métier
     * En les mettant ici, on peut facilement les modifier
     */
    private const MAX_DURATION_HOURS = 4;
    private const OPENING_HOUR = 8;  // 08:00
    private const CLOSING_HOUR = 19; // 19:00
    private const SLOT_DURATION_MINUTES = 30;
    
    /**
     * Jours d'ouverture (1 = lundi, 7 = dimanche)
     */
    private const OPEN_DAYS = [1, 2, 3, 4, 5, 6]; // Lundi à samedi

    /**
     * Constructor avec injection du repository
     * 
     * @param ReservationRepository $reservationRepository
     * Le repository permet d'accéder aux réservations existantes en base
     */
    public function __construct(
        private ReservationRepository $reservationRepository
    ) {}

    /**
     * MÉTHODE PRINCIPALE DE VALIDATION
     * 
     * Valide tous les aspects d'une réservation
     * Retourne un tableau d'erreurs (vide si tout est OK)
     * 
     * @param Reservation $reservation La réservation à valider
     * @param Reservation|null $existingReservation Pour exclure lors d'une modification
     * @return array<string> Tableau des erreurs
     */
    public function validate(Reservation $reservation, ?Reservation $existingReservation = null): array
    {
        $errors = [];

        // Vérification 1 : Les dates sont-elles cohérentes ?
        if (!$this->isEndAfterStart($reservation)) {
            $errors[] = "La date de fin doit être après la date de début.";
        }

        // Vérification 2 : La durée est-elle acceptable ?
        if (!$this->isDurationValid($reservation)) {
            $errors[] = sprintf(
                "La durée maximale est de %d heures. Durée demandée : %d minutes.",
                self::MAX_DURATION_HOURS,
                $reservation->getDurationInMinutes()
            );
        }

        // Vérification 3 : Réserve-t-on dans le futur ?
        if (!$this->isInFuture($reservation)) {
            $errors[] = "Vous ne pouvez pas réserver dans le passé.";
        }

        // Vérification 4 : Les horaires sont-ils corrects ?
        if (!$this->isWithinOpeningHours($reservation)) {
            $errors[] = sprintf(
                "Les réservations sont possibles de %dh à %dh.",
                self::OPENING_HOUR,
                self::CLOSING_HOUR
            );
        }

        // Vérification 5 : Le jour est-il ouvré ?
        if (!$this->isOpenDay($reservation)) {
            $errors[] = "Les réservations sont possibles du lundi au samedi uniquement.";
        }

        // Vérification 6 : Les créneaux sont-ils alignés sur 30 minutes ?
        if (!$this->isSlotAligned($reservation)) {
            $errors[] = "Les créneaux doivent être alignés sur des tranches de 30 minutes (ex: 09:00, 09:30, 10:00...).";
        }

        // Vérification 7 : Y a-t-il un conflit avec une autre réservation ?
        if (!$this->hasNoConflict($reservation, $existingReservation)) {
            $errors[] = "Ce créneau est déjà réservé. Veuillez choisir un autre horaire.";
        }

        return $errors;
    }

    /**
     * RÈGLE 1 : La fin doit être après le début
     * 
     * Exemple valide : 10:00 → 11:00
     * Exemple invalide : 10:00 → 10:00 ou 10:00 → 09:00
     */
    private function isEndAfterStart(Reservation $reservation): bool
    {
        // On compare les timestamps (nombre de secondes depuis 1970)
        return $reservation->getEndAt() > $reservation->getStartAt();
    }

    /**
     * RÈGLE 2 : La durée maximale est de 4 heures
     * 
     * Calcul : (endAt - startAt) en minutes <= 240 minutes (4h)
     */
    private function isDurationValid(Reservation $reservation): bool
    {
        $durationInMinutes = $reservation->getDurationInMinutes();
        $maxDurationInMinutes = self::MAX_DURATION_HOURS * 60;
        
        return $durationInMinutes > 0 && $durationInMinutes <= $maxDurationInMinutes;
    }

    /**
     * RÈGLE 3 : Pas de réservation dans le passé
     * 
     * Compare la date de début avec l'heure actuelle
     */
    private function isInFuture(Reservation $reservation): bool
    {
        $now = new DateTimeImmutable();
        return $reservation->getStartAt() > $now;
    }

    /**
     * RÈGLE 4 : Les réservations doivent être dans les horaires d'ouverture
     * 
     * Ouvert de 08:00 à 19:00
     * La réservation doit commencer à 08:00 ou après
     * ET se terminer à 19:00 ou avant
     */
    private function isWithinOpeningHours(Reservation $reservation): bool
    {
        $startHour = (int) $reservation->getStartAt()->format('H');
        $startMinute = (int) $reservation->getStartAt()->format('i');
        $endHour = (int) $reservation->getEndAt()->format('H');
        $endMinute = (int) $reservation->getEndAt()->format('i');

        // Vérifie que le début est >= 08:00
        $startsOnTime = $startHour > self::OPENING_HOUR || 
                       ($startHour === self::OPENING_HOUR && $startMinute >= 0);

        // Vérifie que la fin est <= 19:00
        $endsOnTime = $endHour < self::CLOSING_HOUR || 
                     ($endHour === self::CLOSING_HOUR && $endMinute === 0);

        return $startsOnTime && $endsOnTime;
    }

    /**
     * RÈGLE 5 : Ouvert du lundi au samedi uniquement
     * 
     * PHP : 1 = lundi, 7 = dimanche
     */
    private function isOpenDay(Reservation $reservation): bool
    {
        $dayOfWeek = (int) $reservation->getStartAt()->format('N');
        return in_array($dayOfWeek, self::OPEN_DAYS, true);
    }

    /**
     * RÈGLE 6 : Les créneaux doivent être alignés sur 30 minutes
     * 
     * Valide : 09:00, 09:30, 10:00, 10:30...
     * Invalide : 09:15, 09:45, 10:17...
     */
    private function isSlotAligned(Reservation $reservation): bool
    {
        $startMinute = (int) $reservation->getStartAt()->format('i');
        $endMinute = (int) $reservation->getEndAt()->format('i');

        // Les minutes doivent être 00 ou 30
        $validMinutes = [0, 30];
        
        return in_array($startMinute, $validMinutes, true) && 
               in_array($endMinute, $validMinutes, true);
    }

    /**
     * RÈGLE 7 : Pas de chevauchement avec d'autres réservations
     * 
     * Deux réservations se chevauchent si :
     * - Réservation A commence pendant Réservation B, OU
     * - Réservation A se termine pendant Réservation B, OU
     * - Réservation A englobe complètement Réservation B
     * 
     * Formule mathématique :
     * Chevauchement SI : (startA < endB) ET (endA > startB)
     * 
     * @param Reservation|null $existingReservation Pour exclure lors d'une modification
     */
    private function hasNoConflict(Reservation $reservation, ?Reservation $existingReservation = null): bool
    {
        // On récupère toutes les réservations du même service
        // qui se passent le même jour
        $conflictingReservations = $this->reservationRepository->findConflictingReservations(
            $reservation->getService(),
            $reservation->getStartAt(),
            $reservation->getEndAt(),
            $existingReservation?->getId() // On exclut la réservation en cours de modification
        );

        // S'il n'y a aucune réservation en conflit, c'est OK
        return count($conflictingReservations) === 0;
    }

    /**
     * Génère tous les créneaux disponibles pour une journée
     * 
     * Cette méthode est utile pour afficher le calendrier
     * Elle retourne tous les créneaux de 30 minutes entre 08:00 et 19:00
     * 
     * @param DateTimeImmutable $date La date pour laquelle générer les créneaux
     * @return array<string> Tableau des horaires (ex: ['08:00', '08:30', '09:00', ...])
     */
    public function generateTimeSlots(DateTimeImmutable $date): array
    {
        $slots = [];
        
        // On part de 08:00 du jour demandé
        $current = $date->setTime(self::OPENING_HOUR, 0);
        // On s'arrête à 19:00 du même jour
        $end = $date->setTime(self::CLOSING_HOUR, 0);

        // Boucle tant qu'on n'a pas atteint la fin
        while ($current < $end) {
            // Format : 08:00, 08:30, 09:00...
            $slots[] = $current->format('H:i');
            // On avance de 30 minutes
            $current = $current->modify(sprintf('+%d minutes', self::SLOT_DURATION_MINUTES));
        }

        return $slots;
    }

    /**
     * Vérifie si un créneau spécifique est disponible
     * 
     * @param int $serviceId L'ID du service
     * @param DateTimeImmutable $startAt Début du créneau
     * @param DateTimeImmutable $endAt Fin du créneau
     * @param int|null $excludeReservationId ID à exclure (pour modification)
     * @return bool True si disponible
     */
    public function isSlotAvailable(
        int $serviceId,
        DateTimeImmutable $startAt,
        DateTimeImmutable $endAt,
        ?int $excludeReservationId = null
    ): bool {
        // On crée une réservation temporaire pour utiliser la validation
        $tempReservation = new Reservation();
        $tempReservation->setStartAt($startAt);
        $tempReservation->setEndAt($endAt);

        // On récupère l'objet Service (simplification, normalement on injecterait ServiceRepository)
        // Pour l'instant on fait juste la vérification de conflit

        $conflicts = $this->reservationRepository->findConflictingReservations(
            $serviceId, // On passe directement l'ID
            $startAt,
            $endAt,
            $excludeReservationId
        );

        return count($conflicts) === 0;
    }
}