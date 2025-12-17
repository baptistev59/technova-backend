<?php

namespace App\Form\Vendor;

use App\Entity\Shop;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\File;

final class ShopProfileType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'constraints' => [
                    new NotBlank(message: 'Le nom de la boutique est requis.'),
                    new Length(max: 255),
                ],
            ])
            ->add('slug', TextType::class, [
                'required' => false,
                'attr' => ['placeholder' => 'boutique-slug'],
            ])
            ->add('description', TextareaType::class, [
                'required' => false,
            ])
            ->add('policies', TextareaType::class, [
                'required' => false,
            ])
            ->add('contactEmail', TextType::class, [
                'constraints' => [
                    new NotBlank(message: 'Un e-mail de contact est requis.'),
                    new Email(message: 'Ce champ doit contenir une adresse e-mail valide.'),
                ],
            ])
            ->add('logoFile', FileType::class, [
                'mapped' => false,
                'required' => false,
                'constraints' => [
                    new File(
                        maxSize: '3M',
                        mimeTypes: ['image/jpeg', 'image/png', 'image/webp'],
                        mimeTypesMessage: 'Formats autorisés : JPG, PNG ou WEBP.'
                    ),
                ],
            ])
            ->add('bannerFile', FileType::class, [
                'mapped' => false,
                'required' => false,
                'constraints' => [
                    new File(
                        maxSize: '5M',
                        mimeTypes: ['image/jpeg', 'image/png', 'image/webp'],
                        mimeTypesMessage: 'Formats autorisés : JPG, PNG ou WEBP.'
                    ),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Shop::class,
            'csrf_protection' => false,
        ]);
    }
}
