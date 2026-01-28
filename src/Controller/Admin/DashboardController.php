<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\AttributeDefinition;
use App\Entity\AuditLog;
use App\Entity\Brand;
use App\Entity\Category;
use App\Entity\Conversation;
use App\Entity\CustomerOrder;
use App\Entity\ProductReviewReport;
use App\Entity\Product;
use App\Entity\ProductReview;
use App\Entity\ReturnRequest;
use App\Entity\Shop;
use App\Entity\User;
use App\Entity\Vendor;
use App\Entity\VatRate;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminDashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\Response;

#[AdminDashboard(routePath: '/admin/ea', routeName: 'admin_ea')]
class DashboardController extends AbstractDashboardController
{
    public function __construct(
        private readonly AdminUrlGenerator $adminUrlGenerator,
    ) {
    }

    public function index(): Response
    {
        $url = $this->adminUrlGenerator
            ->setController(CustomerOrderCrudController::class)
            ->generateUrl();

        return $this->redirect($url);
    }

    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()
            ->setTitle('Technova Backend');
    }

    public function configureMenuItems(): iterable
    {
        yield MenuItem::linkToRoute('Dashboard admin', 'fa fa-chart-line', 'admin_dashboard');

        yield MenuItem::section('Utilisateurs & vendeurs');
        yield MenuItem::subMenu('Utilisateurs', 'fas fa-users')->setSubItems([
            MenuItem::linkToCrud('Clients', 'fas fa-user', User::class),
        ]);
        yield MenuItem::subMenu('Vendeurs', 'fas fa-users')->setSubItems([
            MenuItem::linkToCrud('Vendeurs', 'fas fa-store', Vendor::class),
            MenuItem::linkToCrud('Boutiques', 'fas fa-shop', Shop::class),
        ]);

        yield MenuItem::section('Catalogue');
        yield MenuItem::subMenu('Catalogue', 'fas fa-box-open')->setSubItems([
            MenuItem::linkToCrud('Produits', 'fas fa-box', Product::class),
            MenuItem::linkToCrud('Catégories', 'fas fa-layer-group', Category::class),
            MenuItem::linkToCrud('Marques', 'fas fa-tag', Brand::class),
            MenuItem::linkToCrud('Attributs', 'fas fa-bars', AttributeDefinition::class),
            MenuItem::linkToCrud('Avis clients', 'fas fa-star', ProductReview::class),
            MenuItem::linkToCrud('Taux TVA', 'fas fa-percentage', VatRate::class),
        ]);

        yield MenuItem::section('Commandes');
        yield MenuItem::linkToCrud('Commandes', 'fas fa-shopping-cart', CustomerOrder::class);
        yield MenuItem::linkToCrud('Retours', 'fas fa-rotate-left', ReturnRequest::class);
        yield MenuItem::linkToCrud('Conversations', 'fas fa-comments', Conversation::class);

        yield MenuItem::section('Observabilite');
        yield MenuItem::linkToCrud('Audit log', 'fas fa-clipboard-list', AuditLog::class);
        yield MenuItem::linkToCrud('Signalements avis', 'fas fa-flag', ProductReviewReport::class);
        yield MenuItem::linkToRoute('Logs Monolog', 'fas fa-file-lines', 'admin_logs');
    }
}
