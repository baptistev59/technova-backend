<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\ProductVatRate;
use App\Entity\VatRate;
use App\Entity\Shop;
use App\Repository\CountryRepository;
use App\Repository\VatRateRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Formulaire pour sélectionner un taux TVA pour un produit dans un pays spécifique.
 * 
 * Contrainte: UN SEUL taux par produit/pays
 * 
 * Affiche UNIQUEMENT les taux du vendeur pour éviter erreurs.
 */
class ProductVatRateType extends AbstractType
{
    public function __construct(
        private readonly VatRateRepository $vatRateRepository,
        private readonly CountryRepository $countryRepository
    ) {}

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $shop = $options['shop'];

        // Liste des pays affichés
        $countries = $this->getAvailableCountries($shop);
        
        // Récupérer tous les taux pour le filtrage côté client
        $allRates = $this->vatRateRepository->createQueryBuilder('vr')
            ->where('vr.active = true')
            ->orderBy('vr.countryCode', 'ASC')
            ->addOrderBy('vr.code', 'ASC');
        
        if (null !== $shop) {
            $allRates->andWhere('vr.shop = :shop OR vr.shop IS NULL')
                ->setParameter('shop', $shop);
        }
        
        $ratesData = [];
        foreach ($allRates->getQuery()->getResult() as $rate) {
            $ratesData[] = [
                'id' => $rate->getId(),
                'countryCode' => $rate->getCountryCode(),
                'code' => $rate->getCode(),
                'rate' => $rate->getRate(),
                'label' => sprintf('%s (%s) - %s%%', $rate->getCountryCode(), $rate->getCode(), number_format((float) $rate->getRate(), 2, ',', ' '))
            ];
        }

        $builder
            ->add('countryCode', ChoiceType::class, [
                'label' => 'Pays',
                'choices' => array_flip($countries),  // ['🇧🇪 Belgique' => 'BE', '🇫🇷 France' => 'FR', ...]
                'choice_attr' => static function(string $choice, string $key, string $value): array {
                    return ['data-code' => $value];
                },
                'placeholder' => 'Sélectionner un pays...',
                'help' => 'Sélectionnez le pays où ce taux s\'applique',
                'attr' => [
                    'data-vat-rates' => json_encode($ratesData),
                    'class' => 'country-selector'
                ]
            ])
            ->add('vatRate', EntityType::class, [
                'label' => 'Taux TVA',
                'class' => VatRate::class,
                'choice_attr' => static function(VatRate $vat): array {
                    return ['data-country' => $vat->getCountryCode()];
                },
                'choice_label' => function(VatRate $vat): string {
                    $rate = (float) $vat->getRate();
                    return sprintf(
                        '%s (%s) - %s%%',
                        $vat->getCountryCode(),
                        $vat->getCode(),
                        number_format($rate, 2, ',', ' ')
                    );
                },
                'query_builder' => function(VatRateRepository $repo) use ($shop): object {
                    $qb = $repo->createQueryBuilder('vr')
                        ->where('vr.active = true')
                        ->orderBy('vr.countryCode', 'ASC')
                        ->addOrderBy('vr.code', 'ASC');

                    // Filtrer par shop si fourni
                    if (null !== $shop) {
                        $qb->andWhere('vr.shop = :shop OR vr.shop IS NULL')
                           ->setParameter('shop', $shop);
                    }

                    return $qb;
                },
                'placeholder' => 'Sélectionner un taux TVA...',
                'help' => 'Sélectionnez le taux TVA à appliquer pour ce pays',
                'attr' => [
                    'class' => 'vat-rate-selector'
                ]
            ])
            ->add('active', null, [
                'label' => 'Actif',
                'help' => 'Décochez pour désactiver ce taux sans le supprimer',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ProductVatRate::class,
            'shop' => null,
            'label' => false,
        ]);
        $resolver->setAllowedTypes('shop', ['null', Shop::class]);
    }

    /**
     * Récupère la liste des pays avec taux disponibles du vendeur
     * 
     * Format: ['FR' => '🇫🇷 France', 'DE' => '🇩🇪 Allemagne', ...]
     * 
     * @return array<string, string>
     */
    private function getAvailableCountries(?Shop $shop): array
    {
        $qb = $this->vatRateRepository->createQueryBuilder('vr')
            ->select('DISTINCT vr.countryCode')
            ->where('vr.active = true')
            ->orderBy('vr.countryCode', 'ASC');

        if (null !== $shop) {
            $qb->andWhere('vr.shop = :shop OR vr.shop IS NULL')
               ->setParameter('shop', $shop);
        }

        $results = $qb->getQuery()->getResult();
        $codes = array_values(array_unique(array_map(static fn (array $row): string => $row['countryCode'], $results)));
        $countryMap = $this->countryRepository->getMapByCodes($codes);

        $countries = [];
        foreach ($codes as $code) {
            $flag = $countryMap[$code]['flag'] ?? '🏳️';
            $name = $countryMap[$code]['name'] ?? $code;
            $countries[$code] = "$flag $name";
        }

        return $countries;
    }
}
