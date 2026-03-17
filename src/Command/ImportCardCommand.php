<?php

namespace App\Command;

use App\Entity\Card;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressIndicator;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'import:card',
    description: 'Import cards from CSV file with detailed logging',
)]
class ImportCardCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
        private array $csvHeader = []
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        ini_set('memory_limit', '2G');
        $io = new SymfonyStyle($input, $output);
        $filepath = __DIR__ . '/../../data/AllPrintingsCSVFiles/cards.csv';

        $start = microtime(true);
        $this->logger->info('===== DEBUT IMPORT =====');
        $this->logger->info('File path: ' . $filepath);
        $this->logger->info('Memory limit: 2G');

        $handle = fopen($filepath, 'r');
        if ($handle === false) {
            $this->logger->error('FAILED to open file: ' . $filepath);
            $io->error('File not found');
            return Command::FAILURE;
        }

        $this->logger->info('File opened successfully');

        $i = 0;
        $imported = 0;
        $skipped = 0;
        $malformed = 0;
        $limit = 2000; // LIMITE POUR TESTS

        $this->csvHeader = fgetcsv($handle);
        $this->logger->info('CSV header read: ' . implode(', ', $this->csvHeader));

        // Log début récupération UUIDs
        $uuidStart = microtime(true);
        $this->logger->info('Fetching existing UUIDs from database...');
        $uuidInDatabase = $this->entityManager->getRepository(Card::class)->getAllUuids();
        $uuidEnd = microtime(true);
        $this->logger->info(sprintf(
            'Fetched %d existing UUIDs in %.2f seconds',
            count($uuidInDatabase),
            $uuidEnd - $uuidStart
        ));

        $progressIndicator = new ProgressIndicator($output);
        $progressIndicator->start('Importing cards...');

        while (($row = $this->readCSV($handle)) !== false) {
            // Ligne mal formatée
            if ($row === null) {
                $malformed++;
                continue;
            }

            $i++;

            if (!in_array($row['uuid'], $uuidInDatabase)) {
                try {
                    $this->addCard($row);
                    $imported++;
                } catch (\Exception $e) {
                    $this->logger->error(sprintf(
                        'Ajout impossible de la carte UUID %s: %s',
                        $row['uuid'] ?? 'UNKNOWN',
                        $e->getMessage()
                    ));
                }
            } else {
                $skipped++;
            }

            if ($i % 2000 === 0) {
                $flushStart = microtime(true);
                $this->entityManager->flush();
                $this->entityManager->clear();
                $flushEnd = microtime(true);

                $this->logger->info(sprintf(
                    'Batch %d: Flushed in %.2f seconds (imported: %d, skipped: %d, malformed: %d)',
                    $i / 2000,
                    $flushEnd - $flushStart,
                    $imported,
                    $skipped,
                    $malformed
                ));

                $progressIndicator->advance();
            }

            // LIMITE POUR TESTS
            if ($i >= $limit) {
                $this->logger->warning(sprintf('LIMITE ATTEINTE: Stop à %d cards', $limit));
                break;
            }
        }

        //flush en sortie de boucle
        $flushStart = microtime(true);
        $this->entityManager->flush();
        $flushEnd = microtime(true);
        $this->logger->info(sprintf('Final flush in %.2f seconds', $flushEnd - $flushStart));

        $progressIndicator->finish('Importing cards done.');
        fclose($handle);

        // Statistiques finales
        $end = microtime(true);
        $timeElapsed = $end - $start;

        $this->logger->info('===== IMPORT TERMINE =====');
        $this->logger->info(sprintf('Cartes importées: %d', $imported));
        $this->logger->info(sprintf('Temps total: %.2f seconds', $timeElapsed));
        $this->logger->info(sprintf('Speed: %.0f cards/second', $i / max($timeElapsed, 0.001)));

        $io->success(sprintf(
            'Processed %d cards in %.2f seconds (imported: %d, skipped: %d, malformed: %d)',
            $i,
            $timeElapsed,
            $imported,
            $skipped,
            $malformed
        ));

        return Command::SUCCESS;
    }

    private function readCSV(mixed $handle): array|false|null
    {
        $row = fgetcsv($handle);
        if ($row === false) {
            return false;
        }

        // Vérifier le nombre de colonnes
        if (count($row) !== count($this->csvHeader)) {
            $this->logger->warning(sprintf(
                'Skipping malformed row (expected %d columns, got %d)',
                count($this->csvHeader),
                count($row)
            ));
            return null; // null = ligne malformée
        }

        return array_combine($this->csvHeader, $row);
    }

    private function addCard(array $row): void
    {
        $card = new Card();
        $card->setUuid($row['uuid']);
        $card->setManaValue($row['manaValue']);
        $card->setManaCost($row['manaCost']);
        $card->setName($row['name']);
        $card->setRarity($row['rarity']);
        $card->setSetCode($row['setCode']);
        $card->setSubtype($row['subtypes']);
        $card->setText($row['text']);
        $card->setType($row['type']);

        $this->entityManager->persist($card);
    }
}