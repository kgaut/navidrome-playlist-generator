<?php

namespace App\Controller;

use App\Entity\RunHistory;
use App\Repository\RunHistoryRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class HistoryController extends AbstractController
{
    private const PER_PAGE = 50;

    #[Route('/history', name: 'app_history', methods: ['GET'])]
    public function index(Request $request, RunHistoryRepository $repository): Response
    {
        $filters = [
            'type' => $request->query->get('type'),
            'status' => $request->query->get('status'),
            'q' => $request->query->get('q'),
        ];
        $page = max(1, (int) $request->query->get('page', 1));

        $result = $repository->findFilteredPaginated($filters, $page, self::PER_PAGE);
        $totalPages = (int) ceil(max(1, $result['total']) / self::PER_PAGE);

        return $this->render('history/index.html.twig', [
            'items' => $result['items'],
            'total' => $result['total'],
            'page' => $page,
            'total_pages' => $totalPages,
            'filters' => $filters,
            'types' => [
                RunHistory::TYPE_PLAYLIST => 'Playlist',
                RunHistory::TYPE_STATS => 'Stats',
                RunHistory::TYPE_LASTFM_IMPORT => 'Import Last.fm',
            ],
            'statuses' => [
                RunHistory::STATUS_SUCCESS => 'Succès',
                RunHistory::STATUS_ERROR => 'Erreur',
                RunHistory::STATUS_SKIPPED => 'Skip',
            ],
        ]);
    }

    #[Route('/history/{id}', name: 'app_history_detail', methods: ['GET'])]
    public function detail(RunHistory $entry): Response
    {
        return $this->render('history/detail.html.twig', [
            'entry' => $entry,
        ]);
    }
}
