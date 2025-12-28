<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\User;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\ArrayField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\EmailField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

class UserCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return User::class;
    }

    public function configureActions(Actions $actions): Actions
    {
        $reset2fa = Action::new('reset2fa', 'Reset 2FA', 'fa fa-key')
            ->linkToUrl(function (User $user): string {
                return $this->urlGenerator->generate('admin_user_2fa_reset', [
                    'id' => $user->getId(),
                    '_token' => $this->csrfTokenManager->getToken('reset2fa'.$user->getId())->getValue(),
                ]);
            })
            ->addCssClass('btn btn-warning')
            ->displayIf(static fn (User $user): bool => null !== $user->getTotpSecret());

        return $actions
            ->add(Action::INDEX, Action::DETAIL)
            ->add(Action::EDIT, Action::DETAIL)
            ->add(Action::DETAIL, $reset2fa);
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id')->onlyOnIndex(),
            EmailField::new('email'),
            TextField::new('firstname'),
            TextField::new('lastname'),
            TextField::new('phone')->hideOnIndex(),
            BooleanField::new('isDeleted')->hideOnIndex(),
            BooleanField::new('newsletterOptIn')->hideOnIndex(),
            ArrayField::new('roles')->hideOnIndex(),
        ];
    }

    /*
    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id'),
            TextField::new('title'),
            TextEditorField::new('description'),
        ];
    }
    */
}
