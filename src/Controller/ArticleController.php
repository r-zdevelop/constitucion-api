<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\ArticleRepositoryInterface;
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
     * Constructor injection for repository dependency
     *
     * Following Dependency Inversion Principle: depend on abstraction (interface), not concrete class.
     */
    public function __construct(
        private readonly ArticleRepositoryInterface $articleRepository
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
     *
     * @param Article[] $articles
     * @return array<string, Article[]>
     */
    private function groupArticlesByChapter(array $articles): array
    {
        $grouped = [];

        foreach ($articles as $article) {
            $chapter = $article->getChapter() ?? 'No Chapter';
            $grouped[$chapter] ??= [];
            $grouped[$chapter][] = $article;
        }

        // Sort by chapter name
        ksort($grouped);

        return $grouped;
    }
}
