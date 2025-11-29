<?php

declare(strict_types=1);

namespace App\Service;

/**
 * ChapterOrderService
 *
 * Manages custom chapter ordering logic.
 * Single Responsibility: Define and apply constitutional chapter ordering rules.
 *
 * This service encapsulates the business rule that chapters must appear in a specific
 * legal/constitutional order rather than alphabetically.
 */
final class ChapterOrderService
{
    /**
     * Defines the official chapter order for Dominican Constitution.
     *
     * Chapters not in this list will appear at the end in alphabetical order.
     */
    private const CHAPTER_ORDER = [
        'Principios fundamentales',
        'Derechos',
        'Garantías',
        'Otros',
    ];

    /**
     * Sort an array of chapter names according to the official order.
     *
     * @param string[] $chapters Array of chapter names
     * @return string[] Sorted array with official order first, then alphabetically
     */
    public function sortChapters(array $chapters): array
    {
        usort($chapters, $this->getChapterComparator());
        return $chapters;
    }

    /**
     * Sort an associative array (chapter => articles) by chapter order.
     *
     * @param array<string, mixed> $chapterGroups Associative array with chapter names as keys
     * @return array<string, mixed> Sorted associative array preserving keys and values
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

        // If chapter is in the official list, return its position
        // Otherwise, return a very high number so it sorts at the end
        return $position !== false ? $position : PHP_INT_MAX;
    }

    /**
     * Creates a comparator function for sorting chapters.
     *
     * Sorting logic:
     * 1. Official chapters (in CHAPTER_ORDER) come first, in defined order
     * 2. Other chapters come after, sorted alphabetically
     *
     * @return callable(string, string): int Comparator function for usort/uksort
     */
    private function getChapterComparator(): callable
    {
        return function (string $a, string $b): int {
            $priorityA = $this->getChapterPriority($a);
            $priorityB = $this->getChapterPriority($b);

            // If both chapters have different priorities, sort by priority
            if ($priorityA !== $priorityB) {
                return $priorityA <=> $priorityB;
            }

            // If both have same priority (both unlisted), sort alphabetically
            return $a <=> $b;
        };
    }
}
