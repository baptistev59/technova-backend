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
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AsCommand(name: 'app:images:find-unsplash-replacements', description: 'Find replacements for broken Unsplash images and export CSV')]
final class FindUnsplashReplacementsCommand extends Command
{
    public function __construct(
        private readonly Connection $connection,
        private readonly HttpClientInterface $httpClient,
        private readonly UnsplashUrlNormalizer $normalizer,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('output', 'o', InputOption::VALUE_REQUIRED, 'Output CSV file path', 'var/unsplash-replacements.csv');
        $this->addOption('per-page', null, InputOption::VALUE_REQUIRED, 'Number of search results per missing image', 3);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $outPath = (string) $input->getOption('output');
        $perPage = (int) $input->getOption('per-page');

        $rows = $this->connection->fetchAllAssociative('SELECT url, occurrences FROM external_image_error WHERE url LIKE ? ORDER BY occurrences DESC', ['%images.unsplash.com/photo-%']);

        $accessKey = (string) ($_ENV['UNSPLASH_ACCESS_KEY'] ?? getenv('UNSPLASH_ACCESS_KEY') ?: '');
        $useApi = $accessKey !== '';

        $fp = fopen($outPath, 'w');
        if (!$fp) {
            $output->writeln('<error>Cannot open output file: '.$outPath.'</error>');
            return Command::FAILURE;
        }

        fputcsv($fp, ['original_url', 'occurrences', 'id', 'exists_on_unsplash', 'replacement_ids', 'replacement_urls']);

        foreach ($rows as $r) {
            $url = $r['url'];
            $occ = (int) $r['occurrences'];
            $norm = $this->normalizer->normalize($url);
            if (null === $norm) {
                fputcsv($fp, [$url, $occ, '', 'no', '', '']);
                continue;
            }

            $id = $norm['id'];
            $exists = 'unknown';
            $replacementIds = [];
            $replacementUrls = [];

            if ($useApi) {
                try {
                    $resp = $this->httpClient->request('GET', 'https://api.unsplash.com/photos/'.$id, [
                        'headers' => ['Authorization' => 'Client-ID '.$accessKey],
                        'timeout' => 10,
                    ]);

                    if (200 === $resp->getStatusCode()) {
                        $exists = 'yes';
                        $data = $resp->toArray();
                        $replacementIds[] = $data['id'];
                        $replacementUrls[] = $data['urls']['regular'] ?? ($data['urls']['full'] ?? '');
                    } elseif (404 === $resp->getStatusCode()) {
                        $exists = 'no';
                        // attempt search by slug from original path
                        $slug = '';
                        if (preg_match('#/photo-'.preg_quote($id,'#').'-([a-z0-9-]+)#i', $url, $m)) {
                            $slug = str_replace('-', ' ', $m[1]);
                        }

                        $query = $slug ?: 'product';
                        $s = $this->httpClient->request('GET', 'https://api.unsplash.com/search/photos', [
                            'headers' => ['Authorization' => 'Client-ID '.$accessKey],
                            'query' => ['query' => $query, 'per_page' => $perPage],
                            'timeout' => 10,
                        ]);

                        if (200 === $s->getStatusCode()) {
                            $res = $s->toArray();
                            foreach ($res['results'] ?? [] as $item) {
                                $replacementIds[] = $item['id'];
                                $replacementUrls[] = $item['urls']['regular'] ?? '';
                            }
                        }
                    } else {
                        $exists = (string) $resp->getStatusCode();
                    }
                } catch (\Throwable $e) {
                    $exists = 'error';
                }
            } else {
                $exists = 'api_key_missing';
            }

            fputcsv($fp, [$url, $occ, $id, $exists, implode(';', $replacementIds), implode(';', $replacementUrls)]);
        }

        fclose($fp);

        $output->writeln(sprintf('Wrote %s', $outPath));
        if (!$useApi) {
            $output->writeln('<comment>No UNSPLASH_ACCESS_KEY found — CSV contains ids to search manually.</comment>');
        }

        return Command::SUCCESS;
    }
}
