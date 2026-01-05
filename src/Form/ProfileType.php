<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Address;
use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;

class ProfileType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var Address $primaryAddress */
        $primaryAddress = $options['primary_address'];

        $builder
            ->add('firstname', TextType::class, [
                'label' => 'Prénom',
                'constraints' => [
                    new NotBlank(message: 'Le prénom est requis.'),
                    new Length(min: 2, max: 255, minMessage: 'Le prénom doit contenir au moins {{ limit }} caractères.'),
                ],
            ])
            ->add('lastname', TextType::class, [
                'label' => 'Nom',
                'constraints' => [
                    new NotBlank(message: 'Le nom est requis.'),
                    new Length(min: 2, max: 255, minMessage: 'Le nom doit contenir au moins {{ limit }} caractères.'),
                ],
            ])
            ->add('phone', TelType::class, [
                'label' => 'Téléphone',
                'required' => false,
                'constraints' => [
                    new Regex(
                        pattern: '/^$|^[0-9+().\s-]{6,25}$/',
                        message: 'Le téléphone doit contenir uniquement des chiffres et caractères usuels.',
                    ),
                ],
            ])
            ->add('avatarFile', FileType::class, [
                'label' => 'Avatar',
                'mapped' => false,
                'required' => false,
                'help' => 'PNG, JPG ou AVIF jusqu’à 2 Mo',
                'constraints' => [
                    new File(
                        maxSize: '2M',
                        mimeTypes: ['image/jpeg', 'image/png', 'image/avif'],
                        mimeTypesMessage: 'Formats autorisés : JPG, PNG ou AVIF.',
                    ),
                ],
            ])
            ->add('avatarPath', HiddenType::class, [
                'required' => false,
            ])
            ->add('newsletterOptIn', CheckboxType::class, [
                'label' => 'Recevoir les nouveautés TechNova',
                'required' => false,
            ])
            ->add('primaryAddress', AddressType::class, [
                'mapped' => false,
                'data' => $primaryAddress,
                'label' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
            'primary_address' => fn () => (new Address())
                ->setIsDefault(true)
                ->setIsBilling(true)
                ->setIsShipping(true),
        ]);
    }
}
