<?php

namespace App\Controller;

use App\Entity\PlaylistDefinition;
use App\Form\PlaylistDefinitionType;
use App\Generator\GeneratorRegistry;
use App\Navidrome\NavidromeRepository;
use App\Repository\PlaylistDefinitionRepository;
use App\Service\PlaylistRunner;
use App\Service\SettingsService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class PlaylistDefinitionController extends AbstractController
{
    #[Route('/playlist/new', name: 'app_playlist_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $def = new PlaylistDefinition();
        return $this->handleForm($request, $em, $def, isNew: true);
    }

    #[Route('/playlist/{id}/edit', name: 'app_playlist_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, EntityManagerInterface $em, PlaylistDefinition $def): Response
    {
        return $this->handleForm($request, $em, $def, isNew: false);
    }

    #[Route('/playlist/{id}/delete', name: 'app_playlist_delete', methods: ['POST'])]
    public function delete(Request $request, EntityManagerInterface $em, PlaylistDefinition $def): Response
    {
        if (!$this->isCsrfTokenValid('delete' . $def->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }
        $em->remove($def);
        $em->flush();
        $this->addFlash('success', sprintf('Définition « %s » supprimée.', $def->getName()));

        return $this->redirectToRoute('app_dashboard');
    }

    #[Route('/playlist/{id}/toggle', name: 'app_playlist_toggle', methods: ['POST'])]
    public function toggle(Request $request, EntityManagerInterface $em, PlaylistDefinition $def): Response
    {
        if (!$this->isCsrfTokenValid('toggle' . $def->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }
        $def->setEnabled(!$def->isEnabled());
        $em->flush();

        return $this->redirectToRoute('app_dashboard');
    }

    #[Route('/playlist/{id}/preview', name: 'app_playlist_preview', methods: ['GET'])]
    public function preview(
        PlaylistDefinition $def,
        GeneratorRegistry $registry,
        NavidromeRepository $navidrome,
        SettingsService $settings,
    ): Response {
        if (!$registry->has($def->getGeneratorKey())) {
            $this->addFlash('error', sprintf('Générateur inconnu : "%s"', $def->getGeneratorKey()));
            return $this->redirectToRoute('app_dashboard');
        }
        $generator = $registry->get($def->getGeneratorKey());
        $limit = $def->getLimitOverride() ?? $settings->getDefaultLimit();

        $error = null;
        $tracks = [];
        try {
            $ids = $generator->generate($def->getParameters(), $limit);
            $tracks = $navidrome->summarize($ids);
        } catch (\Throwable $e) {
            $error = $e->getMessage();
        }

        return $this->render('playlist/preview.html.twig', [
            'def' => $def,
            'generator' => $generator,
            'tracks' => $tracks,
            'limit' => $limit,
            'error' => $error,
        ]);
    }

    #[Route('/playlist/{id}/run', name: 'app_playlist_run', methods: ['POST'])]
    public function run(
        Request $request,
        PlaylistDefinition $def,
        PlaylistRunner $runner,
    ): Response {
        if (!$this->isCsrfTokenValid('run' . $def->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }
        try {
            $result = $runner->run($def);
            $this->addFlash('success', sprintf(
                'Playlist « %s » créée dans Navidrome (%d morceaux).',
                $result->playlistName,
                $result->trackCount,
            ));
        } catch (\Throwable $e) {
            $this->addFlash('error', 'Erreur : ' . $e->getMessage());
        }

        return $this->redirectToRoute('app_dashboard');
    }

    private function handleForm(Request $request, EntityManagerInterface $em, PlaylistDefinition $def, bool $isNew): Response
    {
        $form = $this->createForm(PlaylistDefinitionType::class, $def);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($isNew) {
                $em->persist($def);
            }
            $em->flush();
            $this->addFlash('success', $isNew ? 'Définition créée.' : 'Définition mise à jour.');
            return $this->redirectToRoute('app_dashboard');
        }

        return $this->render('playlist/form.html.twig', [
            'form' => $form->createView(),
            'def' => $def,
            'is_new' => $isNew,
        ]);
    }
}
