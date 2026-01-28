<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\VatRate;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\NumberField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

class VatRateCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return VatRate::class;
    }

    public function configureFields(string $pageName): iterable
    {
        $user = $this->getUser();

        // By default show shop as optional association
        $shopField = AssociationField::new('shop')->setRequired(false);

        // If current user is a vendor, tailor the shop field:
        // - if vendor has a single shop -> prefill and hide the field on the form (better UX)
        // - if vendor has multiple shops -> limit choices to their shops and keep field visible
        if (null !== $user && in_array('ROLE_VENDOR', $user->getRoles(), true)) {
            $vendor = $user->getVendor();
            if (null !== $vendor) {
                $shops = $vendor->getShops()->toArray();
                if (1 === count($shops)) {
                    // single shop: hide field on forms (we pre-assign it in createEntity)
                    $shopField = AssociationField::new('shop')->hideOnForm();
                } else {
                    // multiple shops: restrict choices to vendor's shops
                    $shopField = AssociationField::new('shop')
                        ->setFormTypeOptions(['choices' => $shops])
                        ->setRequired(true);
                }
            }
        }

        return [
            IdField::new('id')->onlyOnIndex(),
            $shopField,
            TextField::new('countryCode', 'Pays')->setFormTypeOptions(['attr' => ['maxlength' => 2]]),
            ChoiceField::new('code', 'Classe')->setChoices([
                'STANDARD' => 'STANDARD',
                'REDUCED' => 'REDUCED',
                'ZERO' => 'ZERO',
            ]),
            TextField::new('label'),
            NumberField::new('rate')->setNumDecimals(2)->setHelp('Pourcentage ex: 20.00'),
            BooleanField::new('isDefault', 'Par défaut'),
            BooleanField::new('active'),
            DateTimeField::new('createdAt')->onlyOnDetail(),
            DateTimeField::new('updatedAt')->onlyOnDetail(),
        ];
    }

    /**
     * Pre-fill VatRate->shop for vendor users to improve UX.
     */
    public function createEntity(string $entityFqcn)
    {
        $entity = parent::createEntity($entityFqcn);

        $user = $this->getUser();
        if (null !== $user && in_array('ROLE_VENDOR', $user->getRoles(), true)) {
            $vendor = $user->getVendor();
            if (null !== $vendor) {
                $shops = $vendor->getShops()->toArray();
                if (1 === count($shops)) {
                    // If vendor has a single shop, pre-assign it
                    $entity->setShop($shops[0]);
                }
            }
        }

        return $entity;
    }

    /**
     * Ensure vendors cannot create VatRate for another shop.
     * EasyAdmin calls this when persisting a new entity.
     */
    public function persistEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if ($entityInstance instanceof VatRate) {
            $user = $this->getUser();

            if (null !== $user && in_array('ROLE_VENDOR', $user->getRoles(), true)) {
                $vendor = $user->getVendor();

                if (null === $vendor) {
                    throw new AccessDeniedException('Compte vendeur non trouvé.');
                }

                $shop = $entityInstance->getShop();

                if (null === $shop || null === $shop->getOwner() || $shop->getOwner()->getId() !== $vendor->getId()) {
                    throw new AccessDeniedException('Vous ne pouvez créer/modifier des taux que pour votre boutique.');
                }
            }
        }

        parent::persistEntity($entityManager, $entityInstance);
    }
}
