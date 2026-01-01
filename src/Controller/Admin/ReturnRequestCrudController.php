<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\ReturnRequest;
use App\Enum\ReturnRequestStatus;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextEditorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

final class ReturnRequestCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return ReturnRequest::class;
    }

    public function configureFields(string $pageName): iterable
    {
        $statusChoices = array_combine(
            array_map(static fn (ReturnRequestStatus $status): string => ucfirst($status->value), ReturnRequestStatus::cases()),
            array_map(static fn (ReturnRequestStatus $status): string => $status->value, ReturnRequestStatus::cases())
        );

        return [
            IdField::new('id')->onlyOnIndex(),
            AssociationField::new('order'),
            AssociationField::new('requester'),
            TextField::new('reason'),
            TextEditorField::new('details')->hideOnIndex(),
            ChoiceField::new('status')->setChoices($statusChoices),
            DateTimeField::new('createdAt')->onlyOnIndex(),
        ];
    }
}
