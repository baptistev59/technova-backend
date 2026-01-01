<?php

declare(strict_types=1);

namespace App\Form\Vendor;

use App\Entity\ShippingZone;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

final class ShippingZoneType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Nom de la zone',
                'constraints' => [
                    new NotBlank(message: 'Le nom de la zone est requis.'),
                ],
            ])
            ->add('countries', TextareaType::class, [
                'label' => 'Pays (ISO, un par ligne)',
                'help' => 'Ex: FR, BE, DE. Utilise le code ISO2.',
                'attr' => [
                    'data-trix' => 'false',
                ],
                'constraints' => [
                    new NotBlank(message: 'Indique au moins un pays.'),
                ],
            ])
            ->add('postalCodes', TextareaType::class, [
                'label' => 'Codes postaux (optionnel)',
                'required' => false,
                'help' => 'Ex: 75001-75015, 13*, 33000. Un par ligne.',
                'attr' => [
                    'data-trix' => 'false',
                ],
            ])
            ->add('isActive', CheckboxType::class, [
                'label' => 'Zone active',
                'required' => false,
            ]);

        $builder->get('countries')->addModelTransformer(new CallbackTransformer(
            static fn (?array $value): string => $value ? implode("\n", $value) : '',
            static fn (?string $value): array => ShippingZoneType::splitLines($value)
        ));

        $builder->get('postalCodes')->addModelTransformer(new CallbackTransformer(
            static fn (?array $value): string => $value ? implode("\n", $value) : '',
            static fn (?string $value): ?array => ShippingZoneType::splitLines($value, true)
        ));
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ShippingZone::class,
        ]);
    }

    /**
     * @return array<int, string>|null
     */
    private static function splitLines(?string $value, bool $nullable = false): ?array
    {
        if (null === $value) {
            return $nullable ? null : [];
        }

        $lines = preg_split('/\R+/', $value) ?: [];
        $lines = array_values(array_filter(array_map('trim', $lines), static fn (string $line) => '' !== $line));

        if ([] === $lines && $nullable) {
            return null;
        }

        return $lines;
    }
}
