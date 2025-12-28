<?php

declare(strict_types=1);

namespace App\Form\Vendor;

use App\Entity\Vendor;
use App\Form\AddressType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * Sous-formulaire regroupant les informations légales d'un vendeur.
 */
class VendorType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('companyName', TextType::class, [
                'label' => 'Raison sociale',
                'attr' => [
                    'placeholder' => 'Nom légal de l’entreprise',
                ],
                'constraints' => [
                    new NotBlank(message: 'Merci d’indiquer la raison sociale de ton entreprise.'),
                ],
            ])
            ->add('businessIdType', ChoiceType::class, [
                'label' => 'Type d’identifiant',
                'required' => false,
                'placeholder' => 'Sélectionner',
                'choices' => [
                    'RCS — FR' => 'RCS — FR',
                    'RC — DZ' => 'RC — DZ',
                    'RC — MA' => 'RC — MA',
                    'RNE — TN' => 'RNE — TN',
                    'BCE — BE' => 'BCE — BE',
                    'IDE — CH' => 'IDE — CH',
                    'UID — CH' => 'UID — CH',
                    'HRB — DE' => 'HRB — DE',
                    'HRA — DE' => 'HRA — DE',
                    'ES' => 'ES',
                    'REA — IT' => 'REA — IT',
                    'NIPC — PT' => 'NIPC — PT',
                    'CRN — GB' => 'CRN — GB',
                    'CRO — IE' => 'CRO — IE',
                    'US' => 'US',
                    'BN — CA' => 'BN — CA',
                    'ACN — AU' => 'ACN — AU',
                    'CIN — IN' => 'CIN — IN',
                    'CNPJ — BR' => 'CNPJ — BR',
                    'USCC — CN' => 'USCC — CN',
                    'MERSIS — TR' => 'MERSIS — TR',
                    'AE' => 'AE',
                    'Autre' => 'Autre',
                ],
            ])
            ->add('businessId', TextType::class, [
                'label' => 'Identifiant',
                'required' => false,
                'attr' => [
                    'placeholder' => 'Ex : 512 345 678',
                ],
            ])
            ->add('phone', TextType::class, [
                'label' => 'Téléphone professionnel',
                'required' => false,
                'attr' => [
                    'placeholder' => '+33 1 23 45 67 89',
                ],
            ])
            ->add('email', EmailType::class, [
                'label' => 'Email légal',
                'required' => false,
                'attr' => [
                    'placeholder' => 'legal@tonentreprise.fr',
                ],
            ])
            ->add('website', TextType::class, [
                'label' => 'Site web',
                'required' => false,
                'attr' => [
                    'placeholder' => 'https://tonentreprise.fr',
                ],
            ])
            ->add('address', AddressType::class, [
                'label' => false,
                'required' => false,
                'show_usage_flags' => false,
                'show_label_field' => false,
                'default_label' => 'Shop',
                'required_fields' => ['addressLine1', 'postalCode', 'city', 'country'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Vendor::class,
        ]);
    }
}
