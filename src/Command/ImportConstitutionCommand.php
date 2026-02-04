<?php

declare(strict_types=1);

namespace App\Command;

use App\Domain\Document\Article;
use App\Domain\Document\EmbeddedConcordance;
use App\Domain\Document\LegalDocument;
use App\Domain\Repository\ArticleRepositoryInterface;
use App\Domain\Repository\LegalDocumentRepositoryInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:import-constitution',
    description: 'Imports constitution articles from JSON file into MongoDB'
)]
final class ImportConstitutionCommand extends Command
{
    public function __construct(
        private readonly ArticleRepositoryInterface $articleRepository,
        private readonly LegalDocumentRepositoryInterface $documentRepository
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $jsonPath = dirname(__DIR__, 2) . '/data/constitucion.json';

        if (!file_exists($jsonPath)) {
            $io->error('JSON file not found at: ' . $jsonPath);
            return Command::FAILURE;
        }

        $io->info('Reading JSON file...');

        try {
            $data = json_decode(file_get_contents($jsonPath), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            $io->error('Failed to parse JSON: ' . $e->getMessage());
            return Command::FAILURE;
        }

        // Find or create LegalDocument
        $document = $this->documentRepository->findByNameAndYear(
            $data['name'] ?? 'Unknown',
            (int) ($data['year'] ?? date('Y'))
        );

        if ($document === null) {
            $io->info('Creating new LegalDocument...');

            try {
                $lastModified = new \DateTime($data['last_modified'] ?? 'now');
            } catch (\Exception) {
                $lastModified = new \DateTime();
            }

            $document = new LegalDocument(
                name: $data['name'] ?? 'Unknown',
                documentType: 'constitution',
                year: (int) ($data['year'] ?? date('Y')),
                lastModified: $lastModified,
                totalArticles: (int) ($data['total_articles'] ?? 0)
            );

            $this->documentRepository->save($document);
            $this->documentRepository->flush();

            $io->success('LegalDocument created with ID: ' . $document->getId());
        } else {
            $io->info('Using existing LegalDocument with ID: ' . $document->getId());
        }

        $documentId = $document->getId();
        $seenArticleNumbers = [];
        $importedCount = 0;
        $skippedCount = 0;

        $io->info('Importing articles...');
        $io->progressStart(count($data['articles'] ?? []));

        foreach ($data['articles'] ?? [] as $articleData) {
            $number = (int) $articleData['number'];

            // Skip duplicate numbers in JSON
            if (isset($seenArticleNumbers[$number])) {
                $io->progressAdvance();
                $skippedCount++;
                continue;
            }

            // Skip if already exists in database
            $existingArticle = $this->articleRepository->findByNumber($documentId, $number);
            if ($existingArticle !== null) {
                $seenArticleNumbers[$number] = true;
                $io->progressAdvance();
                $skippedCount++;
                continue;
            }

            $seenArticleNumbers[$number] = true;

            $article = new Article(
                documentId: $documentId,
                articleNumber: $number,
                content: $articleData['content'] ?? '',
                title: $articleData['title'] ?? null
            );

            $article->setChapter($articleData['chapter'] ?? null);

            // Add concordances as embedded documents
            foreach ($articleData['concordancias'] ?? [] as $concordanceData) {
                $concordance = new EmbeddedConcordance(
                    referencedLaw: $concordanceData['law'] ?? '',
                    referencedArticles: $concordanceData['articles'] ?? '',
                    sourceArticleNumber: $number
                );
                $article->addConcordance($concordance);
            }

            $this->articleRepository->save($article);
            $importedCount++;

            $io->progressAdvance();
        }

        $this->articleRepository->flush();

        $io->progressFinish();
        $io->success(sprintf(
            'Import completed. Imported: %d, Skipped: %d',
            $importedCount,
            $skippedCount
        ));

        return Command::SUCCESS;
    }
}
