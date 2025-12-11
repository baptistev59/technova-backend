<?php

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
use Symfony\Component\Validator\Constraints\NotBlank;

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
                ],
            ])
            ->add('slug', TextType::class, [
                'label' => 'Slug (URL)',
                'required' => false,
                'attr' => [
                    'placeholder' => 'ex : novabook-quantum',
                ],
                'help' => 'Utilisé dans l’URL du produit. Laisse vide pour générer automatiquement.',
            ])
            ->add('shortDescription', TextareaType::class, [
                'label' => 'Description courte',
                'required' => false,
                'attr' => [
                    'rows' => 3,
                    'placeholder' => 'Phrase d’accroche affichée dans les listes.',
                ],
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description détaillée',
                'required' => false,
                'attr' => [
                    'rows' => 8,
                    'placeholder' => 'Décris ton produit, ses caractéristiques, son univers…',
                ],
            ])
            ->add('price', NumberType::class, [
                'label' => 'Prix (HT)',
                'html5' => true,
                'scale' => 2,
                'attr' => [
                    'min' => 0,
                    'step' => '0.01',
                ],
                'constraints' => [
                    new NotBlank(message: 'Indique un prix HT.'),
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
            ])
            ->add('stock', IntegerType::class, [
                'label' => 'Stock',
                'attr' => [
                    'min' => 0,
                ],
            ])
            ->add('sku', TextType::class, [
                'label' => 'SKU',
                'required' => false,
                'attr' => [
                    'placeholder' => 'Identifiant interne',
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
                'label' => 'Catégorie',
                'constraints' => [
                    new NotBlank(message: 'Merci de sélectionner une catégorie.'),
                ],
            ])
            ->add('keywords', TextType::class, [
                'label' => 'Mots clés',
                'required' => false,
                'attr' => [
                    'placeholder' => 'tech, usb-c, pliable…',
                ],
                'help' => 'Sépare chaque mot ou expression par une virgule pour améliorer la recherche.',
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
