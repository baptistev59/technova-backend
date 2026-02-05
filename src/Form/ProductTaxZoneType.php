<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\ProductTaxZone;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ProductTaxZoneType extends AbstractType
{
    /**
     * Common European and international country codes
     */
    private const COUNTRIES = [
        // European Union
        'AT' => '🇦🇹 Autriche',
        'BE' => '🇧🇪 Belgique',
        'BG' => '🇧🇬 Bulgarie',
        'HR' => '🇭🇷 Croatie',
        'CY' => '🇨🇾 Chypre',
        'CZ' => '🇨🇿 République tchèque',
        'DK' => '🇩🇰 Danemark',
        'EE' => '🇪🇪 Estonie',
        'FI' => '🇫🇮 Finlande',
        'FR' => '🇫🇷 France',
        'DE' => '🇩🇪 Allemagne',
        'GR' => '🇬🇷 Grèce',
        'HU' => '🇭🇺 Hongrie',
        'IE' => '🇮🇪 Irlande',
        'IT' => '🇮🇹 Italie',
        'LV' => '🇱🇻 Lettonie',
        'LT' => '🇱🇹 Lituanie',
        'LU' => '🇱🇺 Luxembourg',
        'MT' => '🇲🇹 Malte',
        'NL' => '🇳🇱 Pays-Bas',
        'PL' => '🇵🇱 Pologne',
        'PT' => '🇵🇹 Portugal',
        'RO' => '🇷🇴 Roumanie',
        'SK' => '🇸🇰 Slovaquie',
        'SI' => '🇸🇮 Slovénie',
        'ES' => '🇪🇸 Espagne',
        'SE' => '🇸🇪 Suède',
        
        // Europe (non-EU)
        'GB' => '🇬🇧 Royaume-Uni',
        'CH' => '🇨🇭 Suisse',
        'NO' => '🇳🇴 Norvège',
        'IS' => '🇮🇸 Islande',
        
        // International
        'US' => '🇺🇸 États-Unis',
        'CA' => '🇨🇦 Canada',
        'MX' => '🇲🇽 Mexique',
        'BR' => '🇧🇷 Brésil',
        'AU' => '🇦🇺 Australie',
        'NZ' => '🇳🇿 Nouvelle-Zélande',
        'JP' => '🇯🇵 Japon',
        'CN' => '🇨🇳 Chine',
        'IN' => '🇮🇳 Inde',
        'SG' => '🇸🇬 Singapour',
    ];

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('countryCodes', ChoiceType::class, [
                'label' => 'Pays concernés',
                'choices' => self::COUNTRIES,
                'multiple' => true,
                'expanded' => false,
                'attr' => [
                    'class' => 'form-control',
                    'data-placeholder' => 'Sélectionner un ou plusieurs pays',
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
        ]);
    }
}
