<?php

namespace App\Controller;

use App\Service\WrappedService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class WrappedController extends AbstractController
{
    #[Route('/wrapped', name: 'app_wrapped_default', methods: ['GET'])]
    public function defaultYear(): Response
    {
        $year = (int) (new \DateTimeImmutable())->format('Y') - 1;

        return $this->redirectToRoute('app_wrapped', ['year' => $year]);
    }

    #[Route('/wrapped/{year}', name: 'app_wrapped', requirements: ['year' => '\d{4}'], methods: ['GET'])]
    public function show(int $year, WrappedService $service): Response
    {
        $now = (int) (new \DateTimeImmutable())->format('Y');
        if ($year < 2000 || $year > $now) {
            throw $this->createNotFoundException();
        }

        $snapshot = $service->getCached($year);
        $availableYears = range($now, max(2000, $now - 9));

        return $this->render('wrapped/show.html.twig', [
            'year' => $year,
            'snapshot' => $snapshot,
            'available_years' => $availableYears,
        ]);
    }

    #[Route('/wrapped/{year}/refresh', name: 'app_wrapped_refresh', requirements: ['year' => '\d{4}'], methods: ['POST'])]
    public function refresh(int $year, Request $request, WrappedService $service): Response
    {
        $now = (int) (new \DateTimeImmutable())->format('Y');
        if ($year < 2000 || $year > $now) {
            throw $this->createNotFoundException();
        }

        if (!$this->isCsrfTokenValid('wrapped_refresh_' . $year, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        try {
            $service->compute($year);
            $this->addFlash('success', sprintf('Wrapped %d recalculé.', $year));
        } catch (\Throwable $e) {
            $this->addFlash('error', 'Erreur : ' . $e->getMessage());
        }

        return $this->redirectToRoute('app_wrapped', ['year' => $year]);
    }
}
