<?php

declare(strict_types=1);

namespace App\Form\Vendor;

use App\Entity\Shop;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * Formulaire de création de boutique côté vendeur.
 */
class ShopType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Nom de la boutique',
                'attr' => [
                    'maxlength' => 255,
                    'placeholder' => 'Ex : NovaGadgets Paris',
                ],
                'constraints' => [
                    new NotBlank(message: 'Merci d’indiquer un nom de boutique.'),
                    new Length(max: 255),
                ],
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'required' => false,
                'attr' => [
                    'rows' => 4,
                    'placeholder' => 'Présente brièvement ton activité, ton univers, etc.',
                ],
            ])
            ->add('contactEmail', EmailType::class, [
                'label' => 'Email de contact',
                'attr' => [
                    'placeholder' => 'contact@tonshop.fr',
                ],
                'constraints' => [
                    new NotBlank(message: 'L’email de contact est requis.'),
                ],
            ])
            ->add('policies', TextareaType::class, [
                'label' => 'Politique SAV / livraison',
                'required' => false,
                'attr' => [
                    'rows' => 4,
                    'placeholder' => 'Conditions de retour, SAV, garanties…',
                ],
            ])
            ->add('logoFile', FileType::class, [
                'label' => 'Logo (PNG/JPG, 1 Mo max)',
                'mapped' => false,
                'required' => false,
                'constraints' => [
                    new File([
                        'maxSize' => '1M',
                        'mimeTypes' => ['image/png', 'image/jpeg', 'image/webp'],
                        'mimeTypesMessage' => 'Format d’image invalide (PNG, JPG ou WEBP attendus).',
                        'maxSizeMessage' => 'Le logo est trop volumineux ({{ size }}). Limite : {{ limit }}.',
                    ]),
                ],
            ])
            ->add('bannerFile', FileType::class, [
                'label' => 'Bannière (PNG/JPG, 2 Mo max)',
                'mapped' => false,
                'required' => false,
                'constraints' => [
                    new File([
                        'maxSize' => '2M',
                        'mimeTypes' => ['image/png', 'image/jpeg', 'image/webp'],
                        'mimeTypesMessage' => 'Format d’image invalide (PNG, JPG ou WEBP attendus).',
                        'maxSizeMessage' => 'La bannière est trop volumineuse ({{ size }}). Limite : {{ limit }}.',
                    ]),
                ],
            ])
            ->add('owner', VendorType::class, [
                'label' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Shop::class,
        ]);
    }
}
