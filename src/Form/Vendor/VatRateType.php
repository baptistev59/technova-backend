<?php

declare(strict_types=1);

namespace App\Form\Vendor;

use App\Entity\VatRate;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\CountryType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class VatRateType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('countryCode', CountryType::class, [
                'label' => 'Pays',
                'placeholder' => 'Sélectionner un pays',
                'preferred_choices' => ['FR'],
                'required' => true,
                'attr' => ['class' => 'tn-input-select searchable'],
                'choice_translation_locale' => 'fr',
            ])
            ->add('code', TextType::class, [
                'label' => 'Code/Libellé',
                'help' => 'Ex: STANDARD, REDUCED, BOOKS, MEDICINES, ZERO, etc.',
                'required' => true,
                'attr' => [
                    'placeholder' => 'Ex: STANDARD',
                    'maxlength' => 50,
                ],
            ])
            ->add('label', TextType::class, [
                'label' => 'Libellé descriptif',
                'required' => false,
                'help' => 'Description optionnelle pour votre référence',
            ])
            ->add('rate', NumberType::class, [
                'label' => 'Taux (%)',
                'scale' => 2,
                'html5' => true,
                'attr' => ['min' => 0, 'step' => '0.01'],
            ])
            ->add('isDefault', CheckboxType::class, [
                'label' => 'Par défaut',
                'required' => false,
            ])
            ->add('active', CheckboxType::class, [
                'label' => 'Actif',
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => VatRate::class,
        ]);
    }
}
