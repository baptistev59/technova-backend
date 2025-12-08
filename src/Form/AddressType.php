<?php

namespace App\Form;

use App\Entity\Address;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\CountryType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

class AddressType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $requiredFields = $options['required_fields'];

        $require = fn (string $field) => in_array($field, $requiredFields, true);

        if ($options['show_label_field']) {
            $builder->add('label', TextType::class, [
                'label' => 'Nom de l’adresse',
                'required' => false,
                'attr' => ['placeholder' => 'Domicile, bureau…'],
            ]);
        } else {
            $builder->addEventListener(FormEvents::PRE_SET_DATA, static function (FormEvent $event) use ($options): void {
                $address = $event->getData();
                if ($address instanceof Address && !$address->getLabel()) {
                    $address->setLabel($options['default_label']);
                }
            });
        }

        $formatLabel = static function (string $base, bool $required): string {
            return $required ? sprintf('%s <span class="text-red-500">*</span>', $base) : $base;
        };

        $builder
            ->add('addressLine1', TextType::class, [
                'label' => $formatLabel('Adresse', $require('addressLine1')),
                'label_html' => $require('addressLine1'),
                'required' => $require('addressLine1'),
                'constraints' => $require('addressLine1') ? [new NotBlank(message: 'Adresse requise.')] : [],
            ])
            ->add('addressLine2', TextType::class, [
                'label' => 'Complément',
                'required' => false,
            ])
            ->add('postalCode', TextType::class, [
                'label' => $formatLabel('Code postal', $require('postalCode')),
                'label_html' => $require('postalCode'),
                'required' => $require('postalCode'),
                'constraints' => $require('postalCode') ? [new NotBlank(message: 'Code postal requis.')] : [],
            ])
            ->add('city', TextType::class, [
                'label' => $formatLabel('Ville', $require('city')),
                'label_html' => $require('city'),
                'required' => $require('city'),
                'constraints' => $require('city') ? [new NotBlank(message: 'Ville requise.')] : [],
            ])
            ->add('state', TextType::class, [
                'label' => 'Région / Département',
                'required' => false,
            ])
            ->add('country', CountryType::class, [
                'label' => $formatLabel('Pays', $require('country')),
                'label_html' => $require('country'),
                'required' => $require('country'),
                'placeholder' => 'Sélectionner un pays',
                'constraints' => $require('country') ? [new NotBlank(message: 'Pays requis.')] : [],
            ]);

        if ($options['show_usage_flags']) {
            $builder
                ->add('isDefault', CheckboxType::class, [
                    'label' => 'Adresse principale',
                    'required' => false,
                ])
                ->add('isShipping', CheckboxType::class, [
                    'label' => 'Utiliser pour la livraison',
                    'required' => false,
                ])
                ->add('isBilling', CheckboxType::class, [
                    'label' => 'Utiliser pour la facturation',
                    'required' => false,
                ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Address::class,
            'show_usage_flags' => true,
            'show_label_field' => true,
            'default_label' => null,
            'required_fields' => [],
        ]);
        $resolver->setAllowedTypes('show_usage_flags', 'bool');
        $resolver->setAllowedTypes('show_label_field', 'bool');
        $resolver->setAllowedTypes('default_label', ['null', 'string']);
        $resolver->setAllowedTypes('required_fields', 'array');
    }
}
