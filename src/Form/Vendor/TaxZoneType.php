<?php

declare(strict_types=1);

namespace App\Form\Vendor;

use App\Entity\TaxZone;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Count;
use Symfony\Component\Validator\Constraints\NotBlank;

class TaxZoneType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        // Liste étendue des pays commerciaux importants (codes ISO2)
        // Inclut les pays fréquemment utilisés pour le commerce international
        $countries = [
            'AT' => 'Autriche', 'AU' => 'Australie', 'BE' => 'Belgique', 'BR' => 'Brésil',
            'CA' => 'Canada', 'CH' => 'Suisse', 'CN' => 'Chine', 'CL' => 'Chili',
            'CY' => 'Chypre', 'CZ' => 'République Tchèque', 'DE' => 'Allemagne', 'DK' => 'Danemark',
            'EG' => 'Égypte', 'ES' => 'Espagne', 'FI' => 'Finlande', 'FR' => 'France',
            'GB' => 'Royaume-Uni', 'GR' => 'Grèce', 'HK' => 'Hong Kong', 'HR' => 'Croatie',
            'HU' => 'Hongrie', 'IE' => 'Irlande', 'IL' => 'Israël', 'IN' => 'Inde',
            'IT' => 'Italie', 'JP' => 'Japon', 'KR' => 'Corée du Sud', 'LI' => 'Liechtenstein',
            'LT' => 'Lituanie', 'LU' => 'Luxembourg', 'LV' => 'Lettonie', 'MA' => 'Maroc',
            'MT' => 'Malte', 'MX' => 'Mexique', 'MY' => 'Malaisie', 'NL' => 'Pays-Bas',
            'NO' => 'Norvège', 'NZ' => 'Nouvelle-Zélande', 'PL' => 'Pologne', 'PT' => 'Portugal',
            'RO' => 'Roumanie', 'RU' => 'Russie', 'SA' => 'Arabie Saoudite', 'SE' => 'Suède',
            'SG' => 'Singapour', 'SI' => 'Slovénie', 'SK' => 'Slovaquie', 'TH' => 'Thaïlande',
            'TN' => 'Tunisie', 'TR' => 'Turquie', 'US' => 'États-Unis', 'UA' => 'Ukraine',
            'AE' => 'Émirats Arabes Unis', 'ID' => 'Indonésie', 'PH' => 'Philippines',
            'VN' => 'Vietnam', 'ZA' => 'Afrique du Sud', 'AR' => 'Argentine', 'CO' => 'Colombie',
            'CL' => 'Chili'
        ];
        asort($countries);

        $builder
            ->add('name', TextType::class, [
                'label' => 'Nom de la zone',
                'attr' => ['placeholder' => 'Ex: Union Européenne — Standard'],
                'constraints' => [
                    new NotBlank(message: 'Le nom est requis.'),
                ],
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'required' => false,
                'attr' => ['placeholder' => 'Description optionnelle pour tes notes personnelles', 'rows' => 3],
            ])
            ->add('countryCodes', ChoiceType::class, [
                'label' => 'Pays applicables',
                'choices' => array_combine(
                    array_map(fn($code, $name) => "$code — $name", array_keys($countries), $countries),
                    array_keys($countries)
                ),
                'multiple' => true,
                'expanded' => false,
                'attr' => ['class' => 'tn-input-select searchable country-select', 'size' => '15'],
                'constraints' => [
                    new NotBlank(message: 'Sélectionne au moins un pays.'),
                    new Count(min: 1, minMessage: 'Au moins un pays est requis.'),
                ],
            ])
            ->add('taxClass', ChoiceType::class, [
                'label' => 'Classe de TVA',
                'choices' => [
                    'Standard' => 'STANDARD',
                    'Réduit' => 'REDUCED',
                    'Taux zéro' => 'ZERO',
                ],
                'constraints' => [
                    new NotBlank(message: 'La classe de TVA est requise.'),
                ],
            ])
            ->add('rate', NumberType::class, [
                'label' => 'Taux de TVA (%)',
                'scale' => 2,
                'html5' => true,
                'attr' => ['min' => 0, 'step' => '0.01', 'placeholder' => 'Ex: 20.00'],
                'constraints' => [
                    new NotBlank(message: 'Le taux est requis.'),
                ],
            ])
            ->add('active', CheckboxType::class, [
                'label' => 'Zone active',
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => TaxZone::class,
        ]);
    }
}
