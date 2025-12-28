<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Product;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\NumberField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextEditorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class ProductCrudController extends AbstractCrudController
{
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
            TextField::new('sku')->hideOnIndex(),
            TextField::new('barcode')->hideOnIndex(),
            TextField::new('type')->hideOnIndex(),
            TextField::new('keywords')->hideOnIndex(),
            BooleanField::new('isFeatured'),
            BooleanField::new('isPublished'),
            AssociationField::new('category'),
            AssociationField::new('brand')->hideOnIndex(),
            AssociationField::new('shop'),
        ];
    }
}
