<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\ProductReviewReport;
use App\Enum\ReviewReportStatus;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextEditorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

final class ProductReviewReportCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return ProductReviewReport::class;
    }

    public function configureFields(string $pageName): iterable
    {
        $statusChoices = array_combine(
            array_map(static fn (ReviewReportStatus $status): string => ucfirst($status->value), ReviewReportStatus::cases()),
            array_map(static fn (ReviewReportStatus $status): string => $status->value, ReviewReportStatus::cases())
        );

        return [
            IdField::new('id')->onlyOnIndex(),
            AssociationField::new('review'),
            AssociationField::new('reporter'),
            TextField::new('reason'),
            TextEditorField::new('details')->hideOnIndex(),
            ChoiceField::new('status')->setChoices($statusChoices),
            DateTimeField::new('createdAt')->onlyOnIndex(),
        ];
    }
}
