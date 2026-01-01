<?php

declare(strict_types=1);

namespace App\Form\Vendor;

use App\Entity\Brand;
use App\Entity\Category;
use App\Entity\Product;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\All;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\GreaterThanOrEqual;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Range;

class ProductType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Nom du produit',
                'attr' => [
                    'placeholder' => 'Ex : NovaBook Q4',
                    'maxlength' => 255,
                ],
                'constraints' => [
                    new NotBlank(message: 'Merci d’indiquer un nom de produit.'),
                    new Length(min: 2, max: 255),
                ],
            ])
            ->add('slug', TextType::class, [
                'label' => 'Slug (URL)',
                'required' => false,
                'attr' => [
                    'placeholder' => 'ex : novabook-quantum',
                ],
                'help' => 'Utilisé dans l’URL du produit. Laisse vide pour générer automatiquement.',
                'constraints' => [
                    new Length(max: 255),
                ],
            ])
            ->add('shortDescription', TextareaType::class, [
                'label' => 'Description courte',
                'required' => false,
                'attr' => [
                    'rows' => 3,
                    'placeholder' => 'Phrase d’accroche affichée dans les listes.',
                ],
                'constraints' => [
                    new Length(max: 500),
                ],
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description détaillée',
                'required' => false,
                'attr' => [
                    'rows' => 8,
                    'placeholder' => 'Décris ton produit, ses caractéristiques, son univers…',
                    'data-trix' => 'true',
                ],
                'constraints' => [
                    new Length(max: 5000),
                ],
            ])
            ->add('price', NumberType::class, [
                'label' => 'Prix (HT)',
                'required' => false,
                'html5' => true,
                'scale' => 2,
                'attr' => [
                    'min' => 0,
                    'step' => '0.01',
                ],
                'constraints' => [
                    new GreaterThanOrEqual(0),
                ],
            ])
            ->add('promoPrice', NumberType::class, [
                'label' => 'Prix promo (HT)',
                'required' => false,
                'html5' => true,
                'scale' => 2,
                'attr' => [
                    'min' => 0,
                    'step' => '0.01',
                ],
                'help' => 'Optionnel. Laisse vide si tu n’as pas de promotion en cours.',
                'constraints' => [
                    new GreaterThanOrEqual(0),
                ],
            ])
            ->add('promoPercent', NumberType::class, [
                'label' => 'Réduction (%)',
                'mapped' => false,
                'required' => false,
                'html5' => true,
                'scale' => 2,
                'attr' => [
                    'min' => 0,
                    'max' => 100,
                    'step' => '0.1',
                    'placeholder' => 'Ex : 15',
                ],
                'help' => 'Tu peux saisir soit le pourcentage, soit le prix promo : l’autre sera recalculé automatiquement.',
                'constraints' => [
                    new Range(min: 0, max: 100),
                ],
            ])
            ->add('bundleDiscountPercent', NumberType::class, [
                'label' => 'Réduction pack (%)',
                'required' => false,
                'html5' => true,
                'scale' => 2,
                'attr' => [
                    'min' => 0,
                    'max' => 100,
                    'step' => '0.1',
                    'placeholder' => 'Ex : 10',
                ],
                'help' => 'Disponible pour les produits groupés. Applique une remise immédiate sur le total du pack.',
                'constraints' => [
                    new Range(min: 0, max: 100),
                ],
            ])
            ->add('stock', IntegerType::class, [
                'label' => 'Stock',
                'attr' => [
                    'min' => 0,
                ],
                'constraints' => [
                    new GreaterThanOrEqual(0),
                ],
            ])
            ->add('weight', NumberType::class, [
                'label' => 'Poids (kg)',
                'required' => false,
                'html5' => true,
                'scale' => 3,
                'attr' => [
                    'min' => 0,
                    'step' => '0.001',
                    'placeholder' => 'Ex : 1.250',
                ],
                'help' => 'Utilisé pour calculer les frais de livraison.',
                'constraints' => [
                    new GreaterThanOrEqual(0),
                ],
            ])
            ->add('lowStockThreshold', IntegerType::class, [
                'label' => 'Stock faible (seuil)',
                'required' => false,
                'attr' => [
                    'min' => 0,
                    'placeholder' => 'Ex : 10',
                ],
                'constraints' => [
                    new GreaterThanOrEqual(0),
                ],
                'help' => 'Seuil d’alerte stock faible (produit simple ou valeur par défaut des variantes).',
            ])
            ->add('sku', TextType::class, [
                'label' => 'SKU',
                'required' => false,
                'attr' => [
                    'placeholder' => 'Identifiant interne',
                ],
                'constraints' => [
                    new Length(max: 255),
                ],
            ])
            ->add('brand', EntityType::class, [
                'class' => Brand::class,
                'choice_label' => 'name',
                'placeholder' => 'Choisir une marque',
                'required' => false,
                'label' => 'Marque',
            ])
            ->add('category', EntityType::class, [
                'class' => Category::class,
                'choice_label' => 'name',
                'placeholder' => 'Choisir une catégorie',
                'required' => false,
                'label' => 'Catégorie',
            ])
            ->add('keywords', TextType::class, [
                'label' => 'Mots clés',
                'required' => false,
                'attr' => [
                    'placeholder' => 'tech, usb-c, pliable…',
                ],
                'help' => 'Sépare chaque mot ou expression par une virgule pour améliorer la recherche.',
                'constraints' => [
                    new Length(max: 500),
                ],
            ])
            ->add('type', ChoiceType::class, [
                'label' => 'Type de produit',
                'placeholder' => 'Sélectionner un type',
                'choices' => [
                    'Produit simple' => 'simple',
                    'Produit variable' => 'variable',
                    'Produit groupé' => 'grouped',
                ],
                'help' => 'Tu pourras gérer les attributs et variantes pour les produits variables dans une prochaine étape.',
            ])
            ->add('isFeatured', CheckboxType::class, [
                'label' => 'Mettre en avant (produit à la une)',
                'required' => false,
                'help' => 'Active pour afficher ce produit dans la sélection “à la une” de ta boutique.',
            ])
            ->add('isPublished', CheckboxType::class, [
                'label' => 'Publier immédiatement',
                'required' => false,
            ])
            ->add('mainImageFile', FileType::class, [
                'label' => 'Photo principale',
                'mapped' => false,
                'required' => false,
                'constraints' => [
                    new File(
                        maxSize: '3M',
                        maxSizeMessage: 'La photo principale ne doit pas dépasser 3 Mo.',
                        mimeTypes: ['image/jpeg', 'image/png', 'image/webp'],
                        mimeTypesMessage: 'Formats autorisés : JPG, PNG ou WEBP.'
                    ),
                ],
            ])
            ->add('galleryFiles', FileType::class, [
                'label' => 'Galerie produit',
                'mapped' => false,
                'multiple' => true,
                'required' => false,
                'constraints' => [
                    new All([
                        'constraints' => [
                            new File(
                                maxSize: '4M',
                                maxSizeMessage: 'Chaque image de la galerie doit faire moins de 4 Mo.',
                                mimeTypes: ['image/jpeg', 'image/png', 'image/webp'],
                                mimeTypesMessage: 'Formats autorisés : JPG, PNG ou WEBP.'
                            ),
                        ],
                    ]),
                ],
                'help' => 'Ajoute plusieurs images pour présenter ton produit (drag & drop bientôt disponible).',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Product::class,
        ]);
    }
}
