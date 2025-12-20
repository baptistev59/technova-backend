<?php

namespace App\Controller\Admin;

use App\Entity\AttributeDefinition;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class AttributeDefinitionCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return AttributeDefinition::class;
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
            TextField::new('inputType'),
            AssociationField::new('shop')
                ->setLabel('Boutique')
                ->setFormTypeOption('choice_label', 'name'),
        ];
    }
    
}
