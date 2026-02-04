<?php

declare(strict_types=1);

namespace App\Presentation\API\Controller;

use App\Application\Service\ArticleService;
use App\Application\Service\ChapterOrderService;
use App\Domain\Exception\ArticleNotFoundException;
use App\Presentation\API\Response\ArticleResponse;
use App\Presentation\API\Response\PaginatedResponse;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/v1/articles', name: 'api_articles_')]
final class ArticleController extends AbstractController
{
    public function __construct(
        private readonly ArticleService $articleService,
        private readonly ChapterOrderService $chapterOrderService
    ) {
    }

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $page = max(1, (int) $request->query->get('page', 1));
        $limit = min(100, max(10, (int) $request->query->get('limit', 20)));
        $chapter = $request->query->get('chapter');

        $result = $this->articleService->getAllArticlesPaginated($page, $limit, $chapter);

        $response = new PaginatedResponse(
            items: ArticleResponse::fromCollection($result['items']),
            total: $result['total'],
            pages: $result['pages'],
            currentPage: $result['currentPage'],
            itemsPerPage: $limit
        );

        return $this->json($response->toArray(), Response::HTTP_OK);
    }

    #[Route('/search', name: 'search', methods: ['GET'])]
    public function search(Request $request): JsonResponse
    {
        $query = trim((string) $request->query->get('q', ''));
        $page = max(1, (int) $request->query->get('page', 1));
        $limit = min(100, max(10, (int) $request->query->get('limit', 20)));

        if (mb_strlen($query) < 2) {
            return $this->json([
                'type' => 'https://tools.ietf.org/html/rfc7807',
                'title' => 'Bad Request',
                'status' => Response::HTTP_BAD_REQUEST,
                'detail' => 'Search query must be at least 2 characters.',
            ], Response::HTTP_BAD_REQUEST);
        }

        $result = $this->articleService->searchArticlesPaginated($query, $page, $limit);

        $response = new PaginatedResponse(
            items: ArticleResponse::fromCollection($result['items']),
            total: $result['total'],
            pages: $result['pages'],
            currentPage: $result['currentPage'],
            itemsPerPage: $limit
        );

        $data = $response->toArray();
        $data['meta']['searchTerm'] = $result['searchTerm'];

        return $this->json($data, Response::HTTP_OK);
    }

    #[Route('/number/{number}', name: 'by_number', methods: ['GET'], requirements: ['number' => '\d+'])]
    public function byNumber(int $number, Request $request): JsonResponse
    {
        if ($number <= 0) {
            return $this->json([
                'type' => 'https://tools.ietf.org/html/rfc7807',
                'title' => 'Bad Request',
                'status' => Response::HTTP_BAD_REQUEST,
                'detail' => 'Article number must be a positive integer.',
            ], Response::HTTP_BAD_REQUEST);
        }

        $documentId = $request->query->get('documentId');

        try {
            $articles = $this->articleService->findByArticleNumber($number, $documentId);

            if (empty($articles)) {
                return $this->json([
                    'type' => 'https://tools.ietf.org/html/rfc7807',
                    'title' => 'Not Found',
                    'status' => Response::HTTP_NOT_FOUND,
                    'detail' => sprintf('Article number %d not found.', $number),
                ], Response::HTTP_NOT_FOUND);
            }

            return $this->json([
                'count' => count($articles),
                'articles' => ArticleResponse::fromCollection($articles),
            ], Response::HTTP_OK);
        } catch (ArticleNotFoundException $e) {
            return $this->json([
                'type' => 'https://tools.ietf.org/html/rfc7807',
                'title' => 'Not Found',
                'status' => Response::HTTP_NOT_FOUND,
                'detail' => $e->getMessage(),
            ], Response::HTTP_NOT_FOUND);
        }
    }

    #[Route('/chapters', name: 'chapters', methods: ['GET'])]
    public function chapters(): JsonResponse
    {
        $chapters = $this->articleService->getAllChapters();
        $sortedChapters = $this->chapterOrderService->sortChapters($chapters);

        return $this->json([
            'count' => count($sortedChapters),
            'chapters' => $sortedChapters,
        ], Response::HTTP_OK);
    }
}
