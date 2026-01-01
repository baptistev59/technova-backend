<?php

declare(strict_types=1);

namespace App\Form\Vendor;

use App\Entity\ShippingMethod;
use App\Entity\ShippingRate;
use App\Entity\Shop;
use App\Repository\ShippingMethodRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\GreaterThanOrEqual;
use Symfony\Component\Validator\Constraints\NotBlank;

final class ShippingRateType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $shop = $options['shop'];

        $builder
            ->add('method', EntityType::class, [
                'label' => 'Méthode',
                'class' => ShippingMethod::class,
                'choice_label' => 'name',
                'query_builder' => static fn (ShippingMethodRepository $repo) => $repo->createQueryBuilder('method')
                    ->andWhere('method.shop = :shop')
                    ->setParameter('shop', $shop)
                    ->orderBy('method.sortOrder', 'ASC')
                    ->addOrderBy('method.name', 'ASC'),
            ])
            ->add('minWeight', NumberType::class, [
                'label' => 'Poids min (kg)',
                'scale' => 3,
                'constraints' => [
                    new NotBlank(message: 'Le poids minimum est requis.'),
                    new GreaterThanOrEqual(0),
                ],
            ])
            ->add('maxWeight', NumberType::class, [
                'label' => 'Poids max (kg)',
                'scale' => 3,
                'required' => false,
                'constraints' => [
                    new GreaterThanOrEqual(0),
                ],
            ])
            ->add('price', NumberType::class, [
                'label' => 'Tarif (€)',
                'scale' => 2,
                'constraints' => [
                    new NotBlank(message: 'Le tarif est requis.'),
                    new GreaterThanOrEqual(0),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ShippingRate::class,
            'shop' => null,
        ]);

        $resolver->setAllowedTypes('shop', [Shop::class]);
    }
}
