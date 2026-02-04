<?php

declare(strict_types=1);

namespace App\Presentation\API\Controller;

use App\Application\UseCase\Collection\AddArticleToCollectionUseCase;
use App\Application\UseCase\Collection\CreateCollectionUseCase;
use App\Application\UseCase\Collection\DeleteCollectionUseCase;
use App\Application\UseCase\Collection\GetCollectionArticlesUseCase;
use App\Application\UseCase\Collection\GetCollectionUseCase;
use App\Application\UseCase\Collection\GetUserCollectionsUseCase;
use App\Application\UseCase\Collection\RemoveArticleFromCollectionUseCase;
use App\Application\UseCase\Collection\UpdateCollectionUseCase;
use App\Domain\Document\User;
use App\Presentation\API\Request\AddArticleToCollectionRequest;
use App\Presentation\API\Request\CreateCollectionRequest;
use App\Presentation\API\Request\UpdateCollectionRequest;
use App\Presentation\API\Response\ArticleResponse;
use App\Presentation\API\Response\CollectionResponse;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/v1/collections', name: 'api_collections_')]
final class CollectionController extends AbstractController
{
    public function __construct(
        private readonly ValidatorInterface $validator,
        private readonly CreateCollectionUseCase $createCollectionUseCase,
        private readonly GetUserCollectionsUseCase $getUserCollectionsUseCase,
        private readonly GetCollectionUseCase $getCollectionUseCase,
        private readonly UpdateCollectionUseCase $updateCollectionUseCase,
        private readonly DeleteCollectionUseCase $deleteCollectionUseCase,
        private readonly AddArticleToCollectionUseCase $addArticleToCollectionUseCase,
        private readonly RemoveArticleFromCollectionUseCase $removeArticleFromCollectionUseCase,
        private readonly GetCollectionArticlesUseCase $getCollectionArticlesUseCase
    ) {
    }

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $collections = $this->getUserCollectionsUseCase->execute($user);

        return $this->json([
            'count' => count($collections),
            'collections' => CollectionResponse::fromCollection($collections),
        ]);
    }

    #[Route('', name: 'create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $data = json_decode($request->getContent(), true) ?? [];

        $createRequest = new CreateCollectionRequest(
            name: $data['name'] ?? '',
            description: $data['description'] ?? null
        );

        $violations = $this->validator->validate($createRequest);

        if (count($violations) > 0) {
            $errors = [];
            foreach ($violations as $violation) {
                $errors[$violation->getPropertyPath()] = $violation->getMessage();
            }

            return $this->json([
                'type' => 'https://tools.ietf.org/html/rfc7807',
                'title' => 'Validation Error',
                'status' => Response::HTTP_BAD_REQUEST,
                'errors' => $errors,
            ], Response::HTTP_BAD_REQUEST);
        }

        $collection = $this->createCollectionUseCase->execute(
            $user,
            $createRequest->name,
            $createRequest->description
        );

        return $this->json(
            (new CollectionResponse($collection))->toArray(),
            Response::HTTP_CREATED
        );
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(string $id): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $collection = $this->getCollectionUseCase->execute($user, $id);

        return $this->json((new CollectionResponse($collection))->toArray());
    }

    #[Route('/{id}', name: 'update', methods: ['PATCH'])]
    public function update(string $id, Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $data = json_decode($request->getContent(), true) ?? [];

        $updateRequest = new UpdateCollectionRequest(
            name: $data['name'] ?? null,
            description: array_key_exists('description', $data) ? $data['description'] : null
        );

        $violations = $this->validator->validate($updateRequest);

        if (count($violations) > 0) {
            $errors = [];
            foreach ($violations as $violation) {
                $errors[$violation->getPropertyPath()] = $violation->getMessage();
            }

            return $this->json([
                'type' => 'https://tools.ietf.org/html/rfc7807',
                'title' => 'Validation Error',
                'status' => Response::HTTP_BAD_REQUEST,
                'errors' => $errors,
            ], Response::HTTP_BAD_REQUEST);
        }

        $collection = $this->updateCollectionUseCase->execute(
            $user,
            $id,
            $updateRequest->name,
            $updateRequest->description
        );

        return $this->json((new CollectionResponse($collection))->toArray());
    }

    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(string $id): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $this->deleteCollectionUseCase->execute($user, $id);

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }

    #[Route('/{id}/articles', name: 'articles_list', methods: ['GET'])]
    public function listArticles(string $id): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $articles = $this->getCollectionArticlesUseCase->execute($user, $id);

        return $this->json([
            'count' => count($articles),
            'articles' => ArticleResponse::fromCollection($articles),
        ]);
    }

    #[Route('/{id}/articles', name: 'articles_add', methods: ['POST'])]
    public function addArticle(string $id, Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $data = json_decode($request->getContent(), true) ?? [];

        $addRequest = new AddArticleToCollectionRequest(
            articleId: $data['articleId'] ?? ''
        );

        $violations = $this->validator->validate($addRequest);

        if (count($violations) > 0) {
            $errors = [];
            foreach ($violations as $violation) {
                $errors[$violation->getPropertyPath()] = $violation->getMessage();
            }

            return $this->json([
                'type' => 'https://tools.ietf.org/html/rfc7807',
                'title' => 'Validation Error',
                'status' => Response::HTTP_BAD_REQUEST,
                'errors' => $errors,
            ], Response::HTTP_BAD_REQUEST);
        }

        $collection = $this->addArticleToCollectionUseCase->execute(
            $user,
            $id,
            $addRequest->articleId
        );

        return $this->json(
            (new CollectionResponse($collection))->toArray(),
            Response::HTTP_CREATED
        );
    }

    #[Route('/{id}/articles/{articleId}', name: 'articles_remove', methods: ['DELETE'])]
    public function removeArticle(string $id, string $articleId): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $collection = $this->removeArticleFromCollectionUseCase->execute($user, $id, $articleId);

        return $this->json((new CollectionResponse($collection))->toArray());
    }
}
