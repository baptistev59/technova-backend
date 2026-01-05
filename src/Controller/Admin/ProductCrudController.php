<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Product;
use App\Entity\ProductImage;
use App\Image\ImageProfileRegistry;
use App\Image\ImageUploader;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\CollectionField;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\NumberField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextEditorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\InputBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Validator\Constraints\All;
use Symfony\Component\Validator\Constraints\File;

class ProductCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly ImageUploader $imageUploader,
        #[Autowire('%kernel.project_dir%')] private readonly string $projectDir,
        private readonly RequestStack $requestStack,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return Product::class;
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->add(Action::INDEX, Action::DETAIL)
            ->add(Action::EDIT, Action::DETAIL);
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id')->onlyOnIndex(),
            TextField::new('name'),
            TextField::new('slug')->hideOnIndex(),
            TextEditorField::new('shortDescription')->hideOnIndex(),
            TextEditorField::new('description')->hideOnIndex(),
            NumberField::new('price'),
            NumberField::new('promoPrice')->hideOnIndex(),
            NumberField::new('bundleDiscountPercent')->hideOnIndex(),
            NumberField::new('stock'),
            NumberField::new('lowStockThreshold')->hideOnIndex(),
            TextField::new('sku')->hideOnIndex(),
            TextField::new('barcode')->hideOnIndex(),
            TextField::new('type')->hideOnIndex(),
            TextField::new('keywords')->hideOnIndex(),
            BooleanField::new('isFeatured'),
            BooleanField::new('isPublished'),
            AssociationField::new('category'),
            AssociationField::new('brand')->hideOnIndex(),
            AssociationField::new('shop'),
            CollectionField::new('images')
                ->onlyOnDetail()
                ->setLabel('Visuels')
                ->setTemplatePath('admin/field/product_images.html.twig'),
            FormField::addPanel('Visuels')->onlyOnForms(),
            Field::new('images')
                ->onlyOnForms()
                ->setLabel('Visuels existants')
                ->setTemplatePath('admin/field/product_images_form.html.twig'),
            Field::new('mainImageFile')
                ->onlyOnForms()
                ->setFormType(FileType::class)
                ->setLabel('Photo principale')
                ->setHelp('3 Mo max. JPG, PNG, WEBP ou AVIF.')
                ->setFormTypeOptions([
                    'mapped' => false,
                    'required' => false,
                    'attr' => ['accept' => 'image/jpeg,image/png,image/webp,image/avif'],
                    'constraints' => [
                        new File(
                            maxSize: '3M',
                            maxSizeMessage: 'La photo principale ne doit pas dépasser 3 Mo.',
                            mimeTypes: ['image/jpeg', 'image/png', 'image/webp', 'image/avif'],
                            mimeTypesMessage: 'Formats autorisés : JPG, PNG, WEBP ou AVIF.'
                        ),
                    ],
                ]),
            Field::new('galleryFiles')
                ->onlyOnForms()
                ->setFormType(FileType::class)
                ->setLabel('Galerie produit')
                ->setHelp('Ajoute plusieurs images (4 Mo max chacune, JPG/PNG/WEBP/AVIF).')
                ->setFormTypeOptions([
                    'mapped' => false,
                    'required' => false,
                    'multiple' => true,
                    'attr' => ['multiple' => true, 'accept' => 'image/jpeg,image/png,image/webp,image/avif'],
                    'constraints' => [
                        new All([
                            'constraints' => [
                                new File(
                                    maxSize: '4M',
                                    maxSizeMessage: 'Chaque image de la galerie doit faire moins de 4 Mo.',
                                    mimeTypes: ['image/jpeg', 'image/png', 'image/webp', 'image/avif'],
                                    mimeTypesMessage: 'Formats autorisés : JPG, PNG, WEBP ou AVIF.'
                                ),
                            ],
                        ]),
                    ],
                ]),
        ];
    }

    public function persistEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if ($entityInstance instanceof Product) {
            $this->processProductImages($entityInstance);
        }

        parent::persistEntity($entityManager, $entityInstance);
    }

    public function updateEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if ($entityInstance instanceof Product) {
            $this->processProductImages($entityInstance);
        }

        parent::updateEntity($entityManager, $entityInstance);
    }

    private function processProductImages(Product $product): void
    {
        $request = $this->requestStack->getCurrentRequest();
        if (null === $request) {
            return;
        }

        $formName = $this->resolveFormName();
        $formFiles = $request->files->get($formName);
        if ($formFiles instanceof InputBag) {
            $formFiles = $formFiles->all();
        }
        if (!is_array($formFiles)) {
            $formFiles = [];
        }
        $mainUpload = $this->normalizeUploadedFile($formFiles['mainImageFile'] ?? null);
        $galleryUploads = $this->normalizeGalleryUploads($formFiles['galleryFiles'] ?? []);

        $this->removeSelectedProductImages($product, $request);

        if (!$mainUpload instanceof UploadedFile) {
            $this->applyExistingMainSelection($product, $request);
        }

        $maxPosition = $this->getMaxImagePosition($product);

        if ($mainUpload instanceof UploadedFile) {
            foreach ($product->getImages() as $existingImage) {
                if ($existingImage->isMain()) {
                    $existingImage->setIsMain(false);
                }
            }

            $mainImage = $this->createProductImageFromFile($product, $mainUpload);
            $mainImage->setIsMain(true);
            $mainImage->setPosition(0);
            $product->addImage($mainImage);
            ++$maxPosition;
        }

        foreach ($galleryUploads as $file) {
            $image = $this->createProductImageFromFile($product, $file);
            $image->setPosition(++$maxPosition);
            $product->addImage($image);
        }
    }

    private function removeSelectedProductImages(Product $product, Request $request): void
    {
        $payload = $request->request->all();
        $ids = $payload['remove_images'] ?? [];
        if (!is_array($ids)) {
            return;
        }

        $ids = array_values(array_unique(array_filter(array_map(static fn ($value) => is_numeric($value) ? (int) $value : null, $ids), static fn ($value) => null !== $value)));
        if ([] === $ids) {
            return;
        }

        foreach ($product->getImages()->toArray() as $image) {
            $imageId = $image->getId();
            if (null !== $imageId && in_array($imageId, $ids, true)) {
                $this->deleteUploadFile($image->getUrl());
                $product->removeImage($image);
            }
        }
    }

    private function applyExistingMainSelection(Product $product, Request $request): void
    {
        $mainImageId = $request->request->get('main_image_id');
        if ('' === $mainImageId || null === $mainImageId || !is_numeric($mainImageId)) {
            return;
        }

        $targetId = (int) $mainImageId;
        $targetImage = null;
        foreach ($product->getImages() as $image) {
            if ($image->getId() === $targetId) {
                $targetImage = $image;
                break;
            }
        }

        if (null === $targetImage) {
            return;
        }

        foreach ($product->getImages() as $image) {
            $image->setIsMain($image === $targetImage);
        }
    }

    private function getMaxImagePosition(Product $product): int
    {
        $maxPosition = 0;
        foreach ($product->getImages() as $image) {
            $maxPosition = max($maxPosition, $image->getPosition());
        }

        return $maxPosition;
    }

    private function normalizeUploadedFile(mixed $file): ?UploadedFile
    {
        if (!$file instanceof UploadedFile) {
            return null;
        }

        if (!$file->isValid()) {
            $originalName = $file->getClientOriginalName() ?: 'Fichier';
            $this->addFlash('error', sprintf('%s n’a pas pu être téléversé (taille ou configuration).', $originalName));

            return null;
        }

        return $file;
    }

    private function normalizeGalleryUploads(mixed $files): array
    {
        if (!is_iterable($files)) {
            return [];
        }

        $uploads = [];
        foreach ($files as $file) {
            $normalized = $this->normalizeUploadedFile($file);
            if ($normalized instanceof UploadedFile) {
                $uploads[] = $normalized;
            }
        }

        return $uploads;
    }

    private function createProductImageFromFile(Product $product, UploadedFile $file): ProductImage
    {
        $relativePath = $this->imageUploader->upload($file, ImageProfileRegistry::get('product_image'));
        $absolutePath = $this->projectDir.'/public/'.ltrim($relativePath, '/');
        $fileSize = is_file($absolutePath) ? filesize($absolutePath) : null;

        $image = new ProductImage();
        $image->setProduct($product);
        $image->setUrl($relativePath);
        $image->setMimeType('image/webp');
        $image->setFileSize($fileSize ?: null);
        $image->setTitle($product->getName());
        $image->setAlt($product->getName());

        return $image;
    }

    private function deleteUploadFile(?string $relativePath): void
    {
        if (null === $relativePath || '' === $relativePath || !str_starts_with($relativePath, 'uploads/')) {
            return;
        }

        $absolute = $this->projectDir.'/public/'.ltrim($relativePath, '/');
        if (is_file($absolute)) {
            @unlink($absolute);
        }
    }

    private function resolveFormName(): string
    {
        $context = $this->getContext();
        $entity = $context?->getEntity();

        return $entity?->getName() ?? 'Product';
    }
}
