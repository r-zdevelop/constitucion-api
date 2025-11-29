<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\ArticleRepositoryInterface;
use App\Service\ChapterOrderService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * ArticleController
 *
 * Handles article-related web pages.
 * Single Responsibility: Display articles to end users through web interface.
 */
final class ArticleController extends AbstractController
{
    /**
     * Constructor injection for dependencies
     *
     * Following Dependency Inversion Principle: depend on abstraction (interface), not concrete class.
     */
    public function __construct(
        private readonly ArticleRepositoryInterface $articleRepository,
        private readonly ChapterOrderService $chapterOrderService
    ) {
    }

    /**
     * List all articles with optional chapter filter
     *
     * Query parameters:
     * - chapter: Filter articles by chapter name
     *
     * @return Response The rendered articles list page
     */
    #[Route('/articles', name: 'app_articles_list', methods: ['GET'])]
    public function list(Request $request): Response
    {
        // Get all available chapters for the filter dropdown
        $allChapters = $this->articleRepository->findAllChapters();

        // Sort chapters by official constitutional order
        $allChapters = $this->chapterOrderService->sortChapters($allChapters);

        // Get the selected chapter from query string (sanitized by Symfony)
        $selectedChapter = $request->query->get('chapter', '');

        // Filter articles by chapter if selected, otherwise get all
        if ($selectedChapter !== '' && $selectedChapter !== null) {
            $articles = $this->articleRepository->findByChapter($selectedChapter);
        } else {
            $articles = $this->articleRepository->findAll();
        }

        // Group articles by chapter for display
        $articlesByChapter = $this->groupArticlesByChapter($articles);

        return $this->render('article/list.html.twig', [
            'articles' => $articles,
            'articlesByChapter' => $articlesByChapter,
            'allChapters' => $allChapters,
            'selectedChapter' => $selectedChapter,
        ]);
    }

    /**
     * Group articles by their chapter.
     *
     * Articles without a chapter are grouped under 'No Chapter'.
     * Article order (by article number) is preserved within each chapter group.
     *
     * @param Article[] $articles Already ordered by article number from repository
     * @return array<string, Article[]> Chapters sorted by official constitutional order, articles by number within each chapter
     */
    private function groupArticlesByChapter(array $articles): array
    {
        $grouped = [];

        // Group articles while preserving order (articles already ordered by articleNumber)
        foreach ($articles as $article) {
            $chapter = $article->getChapter() ?? 'Sin Capítulo';
            $grouped[$chapter] ??= [];
            $grouped[$chapter][] = $article;
        }

        // Sort chapters by official constitutional order (Constitucionales, Derechos, Garantias, Otros)
        // Articles within each chapter remain ordered by articleNumber
        return $this->chapterOrderService->sortChapterGroups($grouped);
    }
}
