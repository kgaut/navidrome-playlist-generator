<?php

namespace App\Controller;

use App\Entity\RunHistory;
use App\Repository\LastFmHistoryEntryRepository;
use App\Service\LastFmHistoryService;
use App\Service\RunHistoryRecorder;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class LastFmHistoryController extends AbstractController
{
    public function __construct(
        private readonly LastFmHistoryService $service,
        private readonly LastFmHistoryEntryRepository $repository,
        private readonly RunHistoryRecorder $recorder,
    ) {
    }

    #[Route('/stats/lastfm-history', name: 'app_stats_lastfm_history', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $defaultUser = (string) ($_ENV['LASTFM_USER'] ?? getenv('LASTFM_USER') ?: '');
        $user = trim((string) $request->query->get('user', $defaultUser));

        $entries = $user !== '' ? $this->repository->findRecentForUser($user, 100) : [];
        $lastFetched = $user !== '' ? $this->repository->findLastFetchedAt($user) : null;
        $apiKeyConfigured = ((string) ($_ENV['LASTFM_API_KEY'] ?? getenv('LASTFM_API_KEY') ?: '')) !== '';

        return $this->render('stats/lastfm_history.html.twig', [
            'user' => $user,
            'entries' => $entries,
            'last_fetched' => $lastFetched,
            'api_key_configured' => $apiKeyConfigured,
        ]);
    }

    #[Route('/stats/lastfm-history/refresh', name: 'app_stats_lastfm_history_refresh', methods: ['POST'])]
    public function refresh(Request $request): Response
    {
        $defaultUser = (string) ($_ENV['LASTFM_USER'] ?? getenv('LASTFM_USER') ?: '');
        $user = trim((string) $request->request->get('user', $defaultUser));

        if (!$this->isCsrfTokenValid('lastfm_history_refresh', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        if ($user === '') {
            $this->addFlash('error', 'Aucun utilisateur Last.fm — renseignez LASTFM_USER ou passez ?user=… dans l\'URL.');

            return $this->redirectToRoute('app_stats_lastfm_history');
        }

        $apiKey = (string) ($_ENV['LASTFM_API_KEY'] ?? getenv('LASTFM_API_KEY') ?: '');
        if ($apiKey === '') {
            $this->addFlash('error', 'LASTFM_API_KEY non défini — impossible d\'appeler l\'API Last.fm.');

            return $this->redirectToRoute('app_stats_lastfm_history', ['user' => $user]);
        }

        try {
            $count = $this->recorder->record(
                type: RunHistory::TYPE_LASTFM_IMPORT,
                reference: 'history:' . $user,
                label: 'Last.fm history refresh — ' . $user,
                action: fn () => $this->service->refresh($apiKey, $user),
                extractMetrics: static fn (int $n) => ['fetched' => $n, 'kind' => 'history'],
            );
            $this->addFlash('success', sprintf('%d morceaux mis en cache.', $count));
        } catch (\Throwable $e) {
            $this->addFlash('error', 'Erreur lors du refresh : ' . $e->getMessage());
        }

        return $this->redirectToRoute('app_stats_lastfm_history', ['user' => $user]);
    }
}
