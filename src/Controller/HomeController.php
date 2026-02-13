<?php
// src/Controller/HomeController.php

namespace App\Controller;

use App\Repository\ServiceRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class HomeController extends AbstractController
{
 
    #[Route('/', name: 'home')]
    public function index(ServiceRepository $serviceRepository): Response
    {
        // Récupère tous les services, triés par nom
        $services = $serviceRepository->findAllOrdered();

        return $this->render('home/index.html.twig', [
            'services' => $services,
        ]);
    }
}
