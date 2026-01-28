<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\UnsplashUrlNormalizer;
use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:images:apply-replacements', description: 'Apply image URL replacements from CSV to DB (preview by default)')]
final class ApplyImageReplacementsCommand extends Command
{
    public function __construct(
        private readonly Connection $connection,
        private readonly UnsplashUrlNormalizer $normalizer,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('input', 'i', InputOption::VALUE_REQUIRED, 'Input CSV path', 'var/unsplash-replacements.csv');
        $this->addOption('apply', null, InputOption::VALUE_NONE, 'Apply changes to the database (otherwise preview)');
        $this->addOption('backup', null, InputOption::VALUE_REQUIRED, 'Backup CSV path to write before applying', 'var/unsplash-applied-backup.csv');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $path = (string) $input->getOption('input');
        $apply = (bool) $input->getOption('apply');
        $backupPath = (string) $input->getOption('backup');

        if (!is_file($path) || !is_readable($path)) {
            $output->writeln(sprintf('<error>Cannot read input CSV: %s</error>', $path));
            return Command::FAILURE;
        }

        $fp = fopen($path, 'r');
        if (false === $fp) {
            $output->writeln(sprintf('<error>Failed opening %s</error>', $path));
            return Command::FAILURE;
        }

        $header = fgetcsv($fp);
        if (false === $header) {
            $output->writeln('<error>Empty CSV</error>');
            fclose($fp);
            return Command::FAILURE;
        }

        $rows = [];
        while (false !== ($r = fgetcsv($fp))) {
            $rows[] = array_combine($header, $r) ?: [];
        }
        fclose($fp);

        if (empty($rows)) {
            $output->writeln('<comment>No rows found in CSV</comment>');
            return Command::SUCCESS;
        }

        $plan = [];
        foreach ($rows as $r) {
            $original = $r['original_url'] ?? '';
            $replacement = '';

            // Prefer replacement_urls field if present
            if (!empty($r['replacement_urls'])) {
                $parts = array_filter(array_map('trim', explode(';', $r['replacement_urls'])));
                if (!empty($parts)) {
                    $replacement = $parts[0];
                }
            }

            // Fallback: if exists_on_unsplash==yes and id present, reconstruct stable URL
            if ('' === $replacement && (isset($r['exists_on_unsplash']) && 'yes' === $r['exists_on_unsplash']) && !empty($r['id'])) {
                $norm = $this->normalizer->normalize((string) $original);
                if (null !== $norm) {
                    $replacement = $norm['reconstructed'];
                }
            }

            if ($replacement === '') {
                continue;
            }

            $plan[] = ['original' => $original, 'replacement' => $replacement];
        }

        if (empty($plan)) {
            $output->writeln('<comment>No applicable replacements found in CSV.</comment>');
            return Command::SUCCESS;
        }

        $output->writeln(sprintf('<info>Found %d replacements</info>', count($plan)));

        // Preview list
        foreach ($plan as $p) {
            $output->writeln(sprintf(' - %s => %s', $p['original'], $p['replacement']));
        }

        if (!$apply) {
            $output->writeln('<comment>Preview mode — run with --apply to perform updates.</comment>');
            return Command::SUCCESS;
        }

        // Write backup: export existing rows that will be changed
        $bfp = fopen($backupPath, 'w');
        if (false === $bfp) {
            $output->writeln(sprintf('<error>Cannot open backup file: %s</error>', $backupPath));
            return Command::FAILURE;
        }
        fputcsv($bfp, ['table', 'id', 'column', 'old_value', 'new_value']);

        $this->connection->beginTransaction();
        try {
            $totalUpdated = 0;
            foreach ($plan as $p) {
                $orig = $p['original'];
                $new = $p['replacement'];

                // product_image.url
                $rows = $this->connection->fetchAllAssociative('SELECT id, url FROM product_image WHERE url = ?', [$orig]);
                foreach ($rows as $row) {
                    fputcsv($bfp, ['product_image', $row['id'], 'url', $row['url'], $new]);
                    $updated = $this->connection->executeStatement('UPDATE product_image SET url = ? WHERE id = ?', [$new, $row['id']]);
                    $totalUpdated += $updated;
                }

                // product_variant.image_path
                $rows = $this->connection->fetchAllAssociative('SELECT id, image_path FROM product_variant WHERE image_path = ?', [$orig]);
                foreach ($rows as $row) {
                    fputcsv($bfp, ['product_variant', $row['id'], 'image_path', $row['image_path'], $new]);
                    $updated = $this->connection->executeStatement('UPDATE product_variant SET image_path = ? WHERE id = ?', [$new, $row['id']]);
                    $totalUpdated += $updated;
                }
            }

            fclose($bfp);
            $this->connection->commit();

            $output->writeln(sprintf('<info>Applied replacements. Total rows updated: %d</info>', $totalUpdated));
            $output->writeln(sprintf('<info>Backup written to %s</info>', $backupPath));
            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $this->connection->rollBack();
            if (is_resource($bfp)) {
                fclose($bfp);
            }
            $output->writeln('<error>Failed applying replacements: '.$e->getMessage().'</error>');
            return Command::FAILURE;
        }
    }
}
