<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\CustomerOrder;
use App\Enum\OrderStatus;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;

class CustomerOrderCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return CustomerOrder::class;
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->add(Action::INDEX, Action::DETAIL)
            ->add(Action::EDIT, Action::DETAIL);
    }

    public function configureFilters(Filters $filters): Filters
    {
        $choices = array_combine(
            array_map(static fn (OrderStatus $status): string => $status->label(), OrderStatus::cases()),
            array_map(static fn (OrderStatus $status): string => $status->value, OrderStatus::cases())
        );

        return $filters
            ->add(ChoiceFilter::new('status')->setChoices($choices));
    }

    public function configureFields(string $pageName): iterable
    {
        $choices = array_combine(
            array_map(static fn (OrderStatus $status): string => $status->label(), OrderStatus::cases()),
            array_map(static fn (OrderStatus $status): string => $status->value, OrderStatus::cases())
        );

        return [
            IdField::new('id')->onlyOnIndex(),
            TextField::new('reference'),
            AssociationField::new('owner')
                ->setLabel('Client')
                ->setFormTypeOption('choice_label', 'email'),
            ChoiceField::new('status')->setChoices($choices),
            TextField::new('totalAmount'),
            TextField::new('refundId')->onlyOnDetail(),
            DateTimeField::new('refundedAt')->onlyOnDetail(),
        ];
    }
}
