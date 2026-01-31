<?php

declare(strict_types=1);

namespace App\Form\Vendor;

use App\Entity\VatRate;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
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
                // préférer la France pour l'UX; adapter si nécessaire
                'preferred_choices' => ['FR'],
                'required' => true,
                // ajoute une classe pour activer la recherche côté frontend (Select2/TomSelect/Choices.js)
                'attr' => ['class' => 'tn-input-select searchable'],
                // utiliser les traductions FR si disponibles
                'choice_translation_locale' => 'fr',
            ])
            ->add('code', ChoiceType::class, [
                'label' => 'Classe',
                'choices' => [
                    'Standard' => 'STANDARD',
                    'Réduit' => 'REDUCED',
                    'Taux zéro' => 'ZERO',
                ],
            ])
            ->add('label', TextType::class, [
                'label' => 'Libellé',
                'required' => false,
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
