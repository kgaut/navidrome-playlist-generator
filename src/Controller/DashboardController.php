<?php

namespace App\Controller;

use App\Generator\GeneratorRegistry;
use App\Navidrome\NavidromeRepository;
use App\Repository\PlaylistDefinitionRepository;
use App\Service\SettingsService;
use App\Subsonic\SubsonicClient;
use Cron\CronExpression;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class DashboardController extends AbstractController
{
    #[Route('/', name: 'app_dashboard')]
    public function index(
        PlaylistDefinitionRepository $repository,
        GeneratorRegistry $registry,
        NavidromeRepository $navidrome,
        SubsonicClient $subsonic,
        SettingsService $settings,
    ): Response {
        $definitions = $repository->findAllOrdered();

        $rows = [];
        foreach ($definitions as $def) {
            $next = null;
            $schedule = $def->getSchedule();
            if ($schedule) {
                try {
                    $next = (new CronExpression($schedule))->getNextRunDate(new \DateTimeImmutable());
                } catch (\Throwable) {
                    $next = null;
                }
            }
            $rows[] = [
                'def' => $def,
                'generator' => $registry->has($def->getGeneratorKey()) ? $registry->get($def->getGeneratorKey()) : null,
                'next_run' => $next,
            ];
        }

        $health = [
            'navidrome_db' => $navidrome->isAvailable(),
            'has_scrobbles' => $navidrome->isAvailable() && $navidrome->hasScrobblesTable(),
            'subsonic' => $subsonic->ping(),
        ];

        return $this->render('dashboard/index.html.twig', [
            'rows' => $rows,
            'health' => $health,
            'default_limit' => $settings->getDefaultLimit(),
            'default_template' => $settings->getDefaultNameTemplate(),
        ]);
    }
}
