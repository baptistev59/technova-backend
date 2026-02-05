<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Country;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Intl\Countries;

#[AsCommand(
    name: 'app:countries:seed',
    description: 'Populate the country table with all ISO countries and emoji flags.'
)]
class SeedCountriesCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('locale', null, InputOption::VALUE_REQUIRED, 'Locale for country names', 'fr');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $locale = (string) $input->getOption('locale');
        $names = Countries::getNames($locale);

        $repository = $this->entityManager->getRepository(Country::class);
        $count = 0;

        foreach ($names as $code => $name) {
            if (strlen($code) !== 2) {
                continue;
            }

            $flag = $this->buildFlag($code);
            $country = $repository->find($code);

            if (!$country instanceof Country) {
                $country = new Country($code, $name, $flag);
                $this->entityManager->persist($country);
            } else {
                $country->setName($name);
                $country->setFlag($flag);
            }

            $count++;
        }

        $this->entityManager->flush();
        $output->writeln(sprintf('Seeded %d countries (locale: %s).', $count, $locale));

        return Command::SUCCESS;
    }

    private function buildFlag(string $code): string
    {
        $code = strtoupper($code);

        if (strlen($code) !== 2 || preg_match('/[^A-Z]/', $code)) {
            return '🏳️';
        }

        $first = ord($code[0]) - 65;
        $second = ord($code[1]) - 65;

        $base = 0x1F1E6;
        $firstFlag = $this->unicodeChar($base + $first);
        $secondFlag = $this->unicodeChar($base + $second);

        return $firstFlag . $secondFlag;
    }

    private function unicodeChar(int $codepoint): string
    {
        if (function_exists('mb_chr')) {
            return mb_chr($codepoint, 'UTF-8');
        }

        if (class_exists('IntlChar')) {
            return \IntlChar::chr($codepoint);
        }

        return '🏳️';
    }
}
