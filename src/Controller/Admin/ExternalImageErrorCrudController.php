<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\ExternalImageError;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

final class ExternalImageErrorCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return ExternalImageError::class;
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id'),
            TextField::new('url'),
            IntegerField::new('statusCode', 'HTTP'),
            IntegerField::new('occurrences'),
            DateTimeField::new('firstSeen'),
            DateTimeField::new('lastSeen'),
        ];
    }
}
