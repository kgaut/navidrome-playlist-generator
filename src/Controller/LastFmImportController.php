<?php

namespace App\Controller;

use App\Form\LastFmImportType;
use App\LastFm\ImportReport;
use App\LastFm\LastFmImporter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class LastFmImportController extends AbstractController
{
    public function __construct(
        private readonly LastFmImporter $importer,
    ) {
    }

    #[Route('/lastfm/import', name: 'app_lastfm_import', methods: ['GET', 'POST'])]
    public function index(Request $request): Response
    {
        $form = $this->createForm(LastFmImportType::class);
        $form->handleRequest($request);

        $report = null;
        $error = null;

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var array<string, mixed> $data */
            $data = $form->getData();
            $apiKey = (string) ($data['api_key'] ?? '');
            if ($apiKey === '') {
                $apiKey = (string) ($_ENV['LASTFM_API_KEY'] ?? getenv('LASTFM_API_KEY') ?: '');
            }

            if ($apiKey === '') {
                $error = 'Aucune API key fournie (champ vide et LASTFM_API_KEY non défini).';
            } else {
                set_time_limit(0);
                ignore_user_abort(true);
                try {
                    $report = $this->importer->import(
                        apiKey: $apiKey,
                        lastFmUser: (string) $data['lastfm_user'],
                        from: $data['from'] instanceof \DateTimeInterface ? \DateTimeImmutable::createFromInterface($data['from']) : null,
                        to: $data['to'] instanceof \DateTimeInterface ? \DateTimeImmutable::createFromInterface($data['to']) : null,
                        toleranceSeconds: max(0, (int) ($data['tolerance'] ?? 60)),
                        dryRun: (bool) ($data['dry_run'] ?? true),
                        maxScrobbles: $data['max_scrobbles'] !== null ? max(1, (int) $data['max_scrobbles']) : null,
                    );
                } catch (\Throwable $e) {
                    $error = $e->getMessage();
                }
            }
        }

        return $this->render('lastfm/import.html.twig', [
            'form' => $form->createView(),
            'report' => $report,
            'error' => $error,
            'unmatched' => $report instanceof ImportReport ? $report->unmatchedRanking(100) : [],
        ]);
    }
}
