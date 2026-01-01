<?php

declare(strict_types=1);

namespace App\Form\Vendor;

use App\Entity\ShippingMethod;
use App\Entity\ShippingZone;
use App\Entity\Shop;
use App\Repository\ShippingZoneRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\GreaterThanOrEqual;
use Symfony\Component\Validator\Constraints\NotBlank;

final class ShippingMethodType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $shop = $options['shop'];

        $builder
            ->add('name', TextType::class, [
                'label' => 'Nom de la méthode',
                'constraints' => [
                    new NotBlank(message: 'Le nom est requis.'),
                ],
            ])
            ->add('carrierName', TextType::class, [
                'label' => 'Transporteur (optionnel)',
                'required' => false,
            ])
            ->add('zone', EntityType::class, [
                'label' => 'Zone de livraison',
                'class' => ShippingZone::class,
                'choice_label' => 'name',
                'query_builder' => static fn (ShippingZoneRepository $repo) => $repo->createQueryBuilder('zone')
                    ->andWhere('zone.shop = :shop')
                    ->setParameter('shop', $shop)
                    ->orderBy('zone.name', 'ASC'),
            ])
            ->add('minDays', IntegerType::class, [
                'label' => 'Délai min (jours)',
                'required' => false,
                'constraints' => [
                    new GreaterThanOrEqual(0),
                ],
            ])
            ->add('maxDays', IntegerType::class, [
                'label' => 'Délai max (jours)',
                'required' => false,
                'constraints' => [
                    new GreaterThanOrEqual(0),
                ],
            ])
            ->add('sortOrder', IntegerType::class, [
                'label' => 'Ordre d’affichage',
                'required' => false,
                'constraints' => [
                    new GreaterThanOrEqual(0),
                ],
            ])
            ->add('isActive', CheckboxType::class, [
                'label' => 'Méthode active',
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ShippingMethod::class,
            'shop' => null,
        ]);

        $resolver->setAllowedTypes('shop', [Shop::class]);
    }
}
