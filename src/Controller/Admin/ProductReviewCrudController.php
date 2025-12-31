<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\ProductReview;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\NumberField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextEditorField;

class ProductReviewCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return ProductReview::class;
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id')->onlyOnIndex(),
            AssociationField::new('product'),
            AssociationField::new('author')->hideOnIndex(),
            NumberField::new('rating')->setNumDecimals(1),
            BooleanField::new('approved')
                ->renderAsSwitch(false)
                ->setTemplatePath('admin/field/approval_badge.html.twig')
                ->onlyOnIndex(),
            TextEditorField::new('comment')->hideOnIndex(),
            DateTimeField::new('createdAt')->hideOnForm(),
            DateTimeField::new('updatedAt')->hideOnForm(),
        ];
    }
}
