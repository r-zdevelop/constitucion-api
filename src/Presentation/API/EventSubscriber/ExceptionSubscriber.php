<?php

declare(strict_types=1);

namespace App\Presentation\API\EventSubscriber;

use App\Domain\Exception\ArticleAlreadyInCollectionException;
use App\Domain\Exception\ArticleNotFoundException;
use App\Domain\Exception\CollectionAccessDeniedException;
use App\Domain\Exception\CollectionLimitExceededException;
use App\Domain\Exception\CollectionNotFoundException;
use App\Domain\Exception\DuplicateCollectionNameException;
use App\Domain\Exception\DuplicateEmailException;
use App\Domain\Exception\InvalidCredentialsException;
use App\Domain\Exception\UserNotFoundException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;

final class ExceptionSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly string $environment
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::EXCEPTION => ['onKernelException', 0],
        ];
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();
        $request = $event->getRequest();

        // Only handle API routes
        if (!str_starts_with($request->getPathInfo(), '/api/')) {
            return;
        }

        $statusCode = $this->getStatusCode($exception);
        $title = $this->getTitle($statusCode);

        $response = [
            'type' => 'https://tools.ietf.org/html/rfc7807',
            'title' => $title,
            'status' => $statusCode,
            'detail' => $exception->getMessage(),
        ];

        // Add debug information in dev environment
        if ($this->environment === 'dev') {
            $response['debug'] = [
                'exception' => get_class($exception),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => array_slice($exception->getTrace(), 0, 5),
            ];
        }

        $event->setResponse(new JsonResponse($response, $statusCode));
    }

    private function getStatusCode(\Throwable $exception): int
    {
        if ($exception instanceof HttpExceptionInterface) {
            return $exception->getStatusCode();
        }

        return match (true) {
            $exception instanceof ArticleNotFoundException,
            $exception instanceof CollectionNotFoundException,
            $exception instanceof UserNotFoundException => Response::HTTP_NOT_FOUND,

            $exception instanceof DuplicateEmailException,
            $exception instanceof DuplicateCollectionNameException,
            $exception instanceof ArticleAlreadyInCollectionException => Response::HTTP_CONFLICT,

            $exception instanceof InvalidCredentialsException => Response::HTTP_UNAUTHORIZED,

            $exception instanceof CollectionAccessDeniedException => Response::HTTP_FORBIDDEN,

            $exception instanceof CollectionLimitExceededException => Response::HTTP_PAYMENT_REQUIRED,

            $exception instanceof \InvalidArgumentException => Response::HTTP_BAD_REQUEST,

            default => Response::HTTP_INTERNAL_SERVER_ERROR,
        };
    }

    private function getTitle(int $statusCode): string
    {
        return match ($statusCode) {
            Response::HTTP_BAD_REQUEST => 'Bad Request',
            Response::HTTP_UNAUTHORIZED => 'Unauthorized',
            Response::HTTP_PAYMENT_REQUIRED => 'Payment Required',
            Response::HTTP_FORBIDDEN => 'Forbidden',
            Response::HTTP_NOT_FOUND => 'Not Found',
            Response::HTTP_CONFLICT => 'Conflict',
            Response::HTTP_UNPROCESSABLE_ENTITY => 'Unprocessable Entity',
            default => 'Internal Server Error',
        };
    }
}
