<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Article;
use App\Entity\LegalDocument;
use App\Repository\ArticleRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class ImportConstitutionCommand extends Command
{

    protected static $defaultName = 'app:import-constitution';

    protected function configure(): void
    {
        $this->setName(self::$defaultName)
            ->setDescription('Imports constitution articles from JSON file');
    }

    public function __construct(
        private ArticleRepositoryInterface $articleRepository,
        private EntityManagerInterface $entityManager
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $jsonPath = __DIR__ . '/../../data/constitucion.json';
        if (!file_exists($jsonPath)) {
            $output->writeln('<error>JSON file not found.</error>');
            return Command::FAILURE;
        }

        $data = json_decode(file_get_contents($jsonPath), true, 512, JSON_THROW_ON_ERROR);

        // Fetch or create LegalDocument using top-level JSON fields
        $document = $this->entityManager->getRepository(LegalDocument::class)
            ->findOneBy(['name' => $data['name'], 'year' => $data['year']]);

        if (!$document) {
            // Parse last_modified into DateTimeImmutable (constructor expects DateTimeInterface)
            if (!empty($data['last_modified'])) {
                try {
                    // Doctrine 'date' type expects a mutable \DateTime instance
                    $lastModified = new \DateTime($data['last_modified']);
                } catch (\Throwable $e) {
                    // Fallback to current date on parse error
                    $lastModified = new \DateTime();
                }
            } else {
                $lastModified = new \DateTime();
            }

            $document = new LegalDocument(
                $data['name'],
                'constitution',
                (int) $data['year'],
                $lastModified,
                (int) ($data['total_articles'] ?? 0)
            );
            $this->entityManager->persist($document);
            $this->entityManager->flush();
        }

        // Track article numbers seen during this import to avoid duplicates inside the JSON
        $seenArticleNumbers = [];

        foreach ($data['articles'] as $articleData) {
            $number = (int) $articleData['number'];

            // Skip duplicate numbers within the same JSON file
            if (isset($seenArticleNumbers[$number])) {
                $output->writeln(sprintf('<comment>Skipping duplicate article number %d in JSON (already processed in this run).</comment>', $number));
                continue;
            }

            // Skip if article already exists in the database for this document
            $existingArticle = $this->articleRepository->findByNumber($document->getId(), $number);
            if ($existingArticle) {
                $output->writeln(sprintf('<comment>Skipping article number %d because it already exists in the database.</comment>', $number));
                // mark as seen to avoid re-checking during this run
                $seenArticleNumbers[$number] = true;
                continue;
            }

            // mark as seen immediately to prevent duplicates later in the JSON
            $seenArticleNumbers[$number] = true;

            $article = new Article(
                $document,
                $number,
                $articleData['content'],
                $articleData['title'] ?? null
            );
            $article->setChapter($articleData['chapter'] ?? null);

            foreach ($articleData['concordancias'] ?? [] as $concordanceData) {
                // Store concordance as a simple associative array to be saved in Article::concordances (JSON)
                $concordanceArray = [
                    'referencedLaw' => $concordanceData['law'],
                    'referencedArticles' => $concordanceData['articles'],
                    'sourceArticleNumber' => $number,
                    'createdAt' => (new \DateTimeImmutable())->format(\DateTime::ATOM),
                ];

                $article->addConcordance($concordanceArray);
            }

            $this->articleRepository->save($article);
        }

        $this->entityManager->flush();

        $output->writeln('<info>Import completed successfully.</info>');
        return Command::SUCCESS;
    }
}
