<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\ArticleRepositoryInterface;
use App\Service\ArticleService;
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
        private readonly ArticleService $articleService,
        private readonly ChapterOrderService $chapterOrderService
    ) {
    }

    /**
     * Search articles by article number
     *
     * Query parameters:
     * - number: Article number to search for (required, positive integer)
     * - documentId: Optional document ID to narrow search
     *
     * Returns JSON response with matching articles.
     *
     * @return Response JSON response with articles
     */
    #[Route('/api/articles/search-by-number', name: 'api_articles_search_by_number', methods: ['GET'])]
    public function searchByNumber(Request $request): Response
    {
        // Extract and validate article number
        $articleNumber = $request->query->get('number');
        if ($articleNumber === null) {
            return $this->json([
                'error' => 'Missing required parameter: number',
            ], Response::HTTP_BAD_REQUEST);
        }

        // Validate it's a positive integer
        if (!ctype_digit($articleNumber) || (int)$articleNumber <= 0) {
            return $this->json([
                'error' => 'Parameter "number" must be a positive integer',
            ], Response::HTTP_BAD_REQUEST);
        }

        // Optional: document ID filter
        $documentId = $request->query->get('documentId');
        $documentIdInt = null;

        if ($documentId !== null) {
            if (!ctype_digit($documentId) || (int)$documentId <= 0) {
                return $this->json([
                    'error' => 'Parameter "documentId" must be a positive integer',
                ], Response::HTTP_BAD_REQUEST);
            }
            $documentIdInt = (int)$documentId;
        }

        // Execute search via service layer (encapsulates business logic)
        $articles = $this->articleService->findByArticleNumber(
            (int)$articleNumber,
            $documentIdInt
        );

        // Transform to JSON-safe array
        $articlesData = array_map(
            fn($article) => [
                'id' => $article->getId(),
                'articleNumber' => $article->getArticleNumber(),
                'title' => $article->getTitle(),
                'content' => $article->getContent(),
                'chapter' => $article->getChapter(),
                'status' => $article->getStatus(),
                'documentId' => $article->getDocument()->getId(),
            ],
            $articles
        );

        // Return structured JSON response
        return $this->json([
            'count' => count($articles),
            'articles' => $articlesData,
        ], Response::HTTP_OK);
    }

    /**
     * List all articles with optional chapter filter, keyword search, and pagination
     *
     * Query parameters:
     * - chapter: Filter articles by chapter name
     * - search: Keyword search (title + content)
     * - page: Current page number (default: 1)
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

        // Extract query parameters (sanitized by Symfony)
        $selectedChapter = $request->query->get('chapter', '');
        $searchTerm = trim((string) $request->query->get('search', ''));
        $page = max(1, (int) $request->query->get('page', 1));

        // Determine which service method to use based on search term
        if ($searchTerm !== '') {
            // Keyword search with pagination
            $paginationData = $this->articleService->searchArticlesPaginated($searchTerm, $page);
            $articles = $paginationData['items'];
        } else {
            // Standard listing with optional chapter filter and pagination
            $paginationData = $this->articleService->getAllArticlesPaginated($page, 20, $selectedChapter ?: null);
            $articles = $paginationData['items'];
        }

        // Group articles by chapter for display
        $articlesByChapter = $this->groupArticlesByChapter($articles);

        return $this->render('article/list.html.twig', [
            'articles' => $articles,
            'articlesByChapter' => $articlesByChapter,
            'allChapters' => $allChapters,
            'selectedChapter' => $selectedChapter,
            'searchTerm' => $searchTerm,
            'pagination' => $paginationData,
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
