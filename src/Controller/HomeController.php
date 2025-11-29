<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * HomeController
 *
 * Handles the homepage display.
 * Single Responsibility: Display the landing page with project information.
 */
final class HomeController extends AbstractController
{
    /**
     * Display the homepage
     *
     * @return Response The rendered homepage
     */
    #[Route('/', name: 'app_home', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('home/index.html.twig', [
            'project_name' => 'Constitución del Ecuador',
            'project_description' => 'Visualizador de Artículos Constitucionales',
        ]);
    }
}
