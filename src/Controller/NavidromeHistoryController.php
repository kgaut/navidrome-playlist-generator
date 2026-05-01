<?php

namespace App\Controller;

use App\Entity\RunHistory;
use App\Navidrome\NavidromeRepository;
use App\Repository\NavidromeHistoryEntryRepository;
use App\Service\NavidromeHistoryService;
use App\Service\RunHistoryRecorder;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class NavidromeHistoryController extends AbstractController
{
    public function __construct(
        private readonly NavidromeHistoryService $service,
        private readonly NavidromeHistoryEntryRepository $repository,
        private readonly NavidromeRepository $navidrome,
        private readonly RunHistoryRecorder $recorder,
        private readonly string $navidromeUrl,
    ) {
    }

    #[Route('/stats/navidrome-history', name: 'app_stats_navidrome_history', methods: ['GET'])]
    public function index(): Response
    {
        $entries = $this->repository->findRecent(100);
        $lastFetched = $this->repository->findLastFetchedAt();
        $hasScrobbles = $this->navidrome->isAvailable() && $this->navidrome->hasScrobblesTable();

        return $this->render('stats/navidrome_history.html.twig', [
            'entries' => $entries,
            'last_fetched' => $lastFetched,
            'has_scrobbles' => $hasScrobbles,
            'navidrome_url' => rtrim($this->navidromeUrl, '/'),
        ]);
    }

    #[Route('/stats/navidrome-history/refresh', name: 'app_stats_navidrome_history_refresh', methods: ['POST'])]
    public function refresh(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('navidrome_history_refresh', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        if (!$this->navidrome->isAvailable() || !$this->navidrome->hasScrobblesTable()) {
            $this->addFlash('error', 'La table Navidrome `scrobbles` est inaccessible (Navidrome ≥ 0.55 requis).');

            return $this->redirectToRoute('app_stats_navidrome_history');
        }

        try {
            $count = $this->recorder->record(
                type: RunHistory::TYPE_STATS,
                reference: 'navidrome-history',
                label: 'Navidrome history refresh',
                action: fn () => $this->service->refresh(),
                extractMetrics: static fn (int $n) => ['fetched' => $n, 'kind' => 'navidrome-history'],
            );
            $this->addFlash('success', sprintf('%d morceaux mis en cache depuis Navidrome.', $count));
        } catch (\Throwable $e) {
            $this->addFlash('error', 'Erreur lors du refresh : ' . $e->getMessage());
        }

        return $this->redirectToRoute('app_stats_navidrome_history');
    }
}
