<?php

declare(strict_types=1);

namespace App\Form\Vendor;

use App\Entity\Vendor;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;
use Symfony\Component\Validator\Constraints\Url;

final class VendorProfileType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('companyName', TextType::class, [
                'constraints' => [
                    new NotBlank(message: 'Le nom de la société est requis.'),
                    new Length(min: 2, max: 255),
                ],
            ])
            ->add('businessId', TextType::class, [
                'required' => false,
                'attr' => ['placeholder' => 'SIRET/SIREN'],
                'constraints' => [
                    new Length(max: 255),
                ],
            ])
            ->add('businessIdType', TextType::class, [
                'required' => false,
                'constraints' => [
                    new Length(max: 50),
                ],
            ])
            ->add('phone', TextType::class, [
                'required' => false,
                'constraints' => [
                    new Regex(
                        pattern: '/^$|^[0-9+().\s-]{6,25}$/',
                        message: 'Le téléphone doit contenir uniquement des chiffres et caractères usuels.',
                    ),
                ],
            ])
            ->add('email', EmailType::class, [
                'constraints' => [
                    new Email(message: 'Adresse e-mail invalide.'),
                ],
            ])
            ->add('website', TextType::class, [
                'required' => false,
                'constraints' => [
                    new Url(
                        message: 'Le site web doit être une URL valide.',
                        requireTld: true
                    ),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Vendor::class,
            'csrf_protection' => false,
        ]);
    }
}
