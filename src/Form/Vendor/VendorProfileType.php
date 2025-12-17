<?php

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

final class VendorProfileType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('companyName', TextType::class, [
                'constraints' => [
                    new NotBlank(message: 'Le nom de la société est requis.'),
                    new Length(max: 255),
                ],
            ])
            ->add('businessId', TextType::class, [
                'required' => false,
                'attr' => ['placeholder' => 'SIRET/SIREN'],
            ])
            ->add('businessIdType', TextType::class, [
                'required' => false,
            ])
            ->add('phone', TextType::class, [
                'required' => false,
            ])
            ->add('email', EmailType::class, [
                'constraints' => [
                    new Email(message: 'Adresse e-mail invalide.'),
                ],
            ])
            ->add('website', TextType::class, [
                'required' => false,
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
