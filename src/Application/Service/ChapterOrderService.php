<?php

declare(strict_types=1);

namespace App\Application\Service;

final class ChapterOrderService
{
    private const CHAPTER_ORDER = [
        'Principios fundamentales',
        'Derechos',
        'Garantías',
        'Otros',
    ];

    /**
     * Sort an array of chapter names according to the official order.
     *
     * @param string[] $chapters
     * @return string[]
     */
    public function sortChapters(array $chapters): array
    {
        usort($chapters, $this->getChapterComparator());
        return $chapters;
    }

    /**
     * Sort an associative array (chapter => articles) by chapter order.
     *
     * @param array<string, mixed> $chapterGroups
     * @return array<string, mixed>
     */
    public function sortChapterGroups(array $chapterGroups): array
    {
        uksort($chapterGroups, $this->getChapterComparator());
        return $chapterGroups;
    }

    /**
     * Get the position/priority of a chapter in the official order.
     *
     * @return int Position (0-based), or PHP_INT_MAX if not in official list
     */
    public function getChapterPriority(string $chapter): int
    {
        $position = array_search($chapter, self::CHAPTER_ORDER, true);
        return $position !== false ? $position : PHP_INT_MAX;
    }

    /**
     * @return callable(string, string): int
     */
    private function getChapterComparator(): callable
    {
        return function (string $a, string $b): int {
            $priorityA = $this->getChapterPriority($a);
            $priorityB = $this->getChapterPriority($b);

            if ($priorityA !== $priorityB) {
                return $priorityA <=> $priorityB;
            }

            return $a <=> $b;
        };
    }
}
