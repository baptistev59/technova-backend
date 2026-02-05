<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\ProductTaxZone;
use App\Entity\Shop;
use App\Repository\CountryRepository;
use App\Repository\VatRateRepository;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ProductTaxZoneType extends AbstractType
{
    public function __construct(
        private readonly VatRateRepository $vatRateRepository,
        private readonly CountryRepository $countryRepository
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var Shop|null $shop */
        $shop = $options['shop'];
        
        // Get available countries from VatRates
        $availableCountries = $this->getAvailableCountries($shop);

        $builder
            ->add('countryCodes', ChoiceType::class, [
                'label' => 'Pays concernés',
                'choices' => $availableCountries,
                'multiple' => true,
                'expanded' => false,
                'attr' => [
                    'class' => 'form-control',
                    'data-placeholder' => 'Sélectionner un ou plusieurs pays',
                    'size' => min(count($availableCountries), 8),
                ],
                'help' => 'Sélectionner les pays pour lesquels cette configuration s\'applique',
            ])
            ->add('taxClass', ChoiceType::class, [
                'label' => 'Classe TVA',
                'choices' => [
                    'Taux standard' => 'STANDARD',
                    'Taux réduit' => 'REDUCED',
                    'Taux zéro' => 'ZERO',
                ],
                'attr' => [
                    'class' => 'form-control',
                ],
                'help' => 'Classe TVA pour ces pays',
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ProductTaxZone::class,
            'attr' => [
                'class' => 'tax-zone-row',
            ],
            'shop' => null,
        ]);

        $resolver->setAllowedTypes('shop', ['null', Shop::class]);
    }

    /**
     * Get available countries with their VAT rates for the shop.
     * Format: ['FR' => '🇫🇷 France (20%)', 'DE' => '🇩🇪 Allemagne (19%)']
     */
    private function getAvailableCountries(?Shop $shop): array
    {
        $qb = $this->vatRateRepository->createQueryBuilder('vr')
            ->select('vr.countryCode, vr.code, vr.rate')
            ->andWhere('vr.active = true')
            ->orderBy('vr.countryCode', 'ASC')
            ->addOrderBy('vr.code', 'ASC');

        if (null !== $shop) {
            $qb->andWhere('vr.shop = :shop OR vr.shop IS NULL')
               ->setParameter('shop', $shop);
        } else {
            $qb->andWhere('vr.shop IS NULL');
        }

        $rates = $qb->getQuery()->getResult();
        $codes = array_values(array_unique(array_map(static fn (array $row): string => $row['countryCode'], $rates)));
        $countryMap = $this->countryRepository->getMapByCodes($codes);

        // Group by country and get STANDARD rate for display
        $countries = [];
        foreach ($rates as $rate) {
            $code = $rate['countryCode'];

            // Only add each country once (prefer STANDARD rate for label)
            if (!isset($countries[$code]) || $rate['code'] === 'STANDARD') {
                $flag = $countryMap[$code]['flag'] ?? '🏳️';
                $name = $countryMap[$code]['name'] ?? $code;
                $rateValue = number_format((float) $rate['rate'], 1, ',', ' ');

                $countries[$code] = sprintf('%s %s (%s%%)', $flag, $name, $rateValue);
            }
        }

        return $countries;
    }
}
