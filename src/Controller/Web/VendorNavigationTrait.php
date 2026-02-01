<?php

namespace App\Controller\Web;

trait VendorNavigationTrait
{
    private function vendorNavigation(string $activeRoute): array
    {
        return [
            ['label' => 'Accueil', 'icon' => '🏠', 'active' => 'app_vendor_shop_new' === $activeRoute, 'path' => 'app_vendor_shop_new'],
            ['label' => 'Mes produits', 'icon' => '🗂️', 'active' => 'app_vendor_products' === $activeRoute, 'path' => 'app_vendor_products'],
            ['label' => 'Attributs', 'icon' => '🎛️', 'active' => 'app_vendor_attributes' === $activeRoute, 'path' => 'app_vendor_attributes'],
            ['label' => 'Taux TVA', 'icon' => '💱', 'active' => 'app_vendor_vatrates' === $activeRoute, 'path' => 'app_vendor_vatrates'],
            ['label' => 'Zones TVA', 'icon' => '🌍', 'active' => 'app_vendor_taxzones' === $activeRoute, 'path' => 'app_vendor_taxzones'],
            ['label' => 'Commandes', 'icon' => '📦', 'active' => 'app_vendor_orders' === $activeRoute, 'path' => 'app_vendor_orders'],
            ['label' => 'Statistiques', 'icon' => '📊', 'active' => false],
            ['label' => 'Paramètres', 'icon' => '⚙️', 'active' => false],
        ];
    }
}
