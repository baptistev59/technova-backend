<?php

declare(strict_types=1);

namespace App\Form\Vendor;

use App\Entity\AttributeDefinition;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class AttributeDefinitionType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Nom de l’attribut',
            ])
            ->add('slug', TextType::class, [
                'label' => 'Slug',
                'required' => false,
                'help' => 'Généré automatiquement si laissé vide.',
            ])
            ->add('inputType', ChoiceType::class, [
                'label' => 'Type de sélection',
                'choices' => [
                    'Menu déroulant' => 'select',
                    'Boutons' => 'chip',
                    'Radio' => 'radio',
                ],
            ])
            ->add('position', IntegerType::class, [
                'label' => 'Position',
                'required' => false,
            ])
            ->add('values', CollectionType::class, [
                'entry_type' => AttributeValueDefinitionType::class,
                'label' => 'Valeurs disponibles',
                'allow_add' => true,
                'allow_delete' => true,
                'by_reference' => false,
                'prototype' => true,
                'entry_options' => [
                    'label' => false,
                ],
                'attr' => [
                    'data-collection-holder' => 'attribute-values',
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => AttributeDefinition::class,
        ]);
    }
}
