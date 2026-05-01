<?php

namespace App\Controller;

use App\Service\StatsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class StatsController extends AbstractController
{
    #[Route('/stats', name: 'app_stats', methods: ['GET'])]
    public function index(Request $request, StatsService $stats): Response
    {
        $period = (string) $request->query->get('period', StatsService::PERIOD_ALL_TIME);
        if (!isset(StatsService::periods()[$period])) {
            $period = StatsService::PERIOD_ALL_TIME;
        }

        $snapshot = $stats->getCached($period);
        $stale = $snapshot === null;

        return $this->render('stats/index.html.twig', [
            'period' => $period,
            'periods' => StatsService::periods(),
            'snapshot' => $snapshot,
            'stale' => $stale,
        ]);
    }

    #[Route('/stats/refresh', name: 'app_stats_refresh', methods: ['POST'])]
    public function refresh(Request $request, StatsService $stats): Response
    {
        $period = (string) $request->request->get('period', StatsService::PERIOD_ALL_TIME);
        if (!isset(StatsService::periods()[$period])) {
            $period = StatsService::PERIOD_ALL_TIME;
        }

        if (!$this->isCsrfTokenValid('stats_refresh_' . $period, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        try {
            $stats->compute($period);
            $this->addFlash('success', sprintf(
                'Statistiques recalculées pour « %s ».',
                StatsService::periods()[$period],
            ));
        } catch (\Throwable $e) {
            $this->addFlash('error', 'Erreur : ' . $e->getMessage());
        }

        return $this->redirectToRoute('app_stats', ['period' => $period]);
    }
}
