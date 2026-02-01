<?php

declare(strict_types=1);

namespace App\Form\Vendor;

use App\Entity\TaxZone;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CountryType;
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
            ->add('countryCodes', CountryType::class, [
                'label' => 'Pays applicables',
                'multiple' => true,
                'preferred_choices' => ['FR', 'DE', 'IT', 'ES', 'BE', 'NL', 'AT', 'LU'],
                'choice_translation_locale' => 'fr',
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

