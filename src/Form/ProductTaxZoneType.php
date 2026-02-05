<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\ProductTaxZone;
use App\Entity\TaxZone;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ProductTaxZoneType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('taxZone', EntityType::class, [
                'class' => TaxZone::class,
                'choice_label' => 'name',
                'label' => 'Zone TVA',
                'placeholder' => 'Sélectionner une zone TVA',
                'attr' => [
                    'class' => 'form-control',
                ],
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
                'help' => 'Classe TVA pour cette zone',
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
