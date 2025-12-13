<?php

namespace App\Form\Vendor;

use App\Entity\AttributeValueDefinition;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class AttributeValueDefinitionType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('label', TextType::class, [
                'label' => 'Libellé',
                'attr' => [
                    'placeholder' => 'Ex. Bleu TechNova',
                ],
            ])
            ->add('value', TextType::class, [
                'label' => 'Valeur technique',
                'attr' => [
                    'placeholder' => 'Ex. tech-blue',
                ],
                'help' => 'Utilisé pour l’URL et l’API. Uniquement des caractères simples.',
            ])
            ->add('position', IntegerType::class, [
                'label' => 'Position',
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => AttributeValueDefinition::class,
        ]);
    }
}

