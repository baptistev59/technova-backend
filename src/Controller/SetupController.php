<?php

namespace App\Controller;

use App\Entity\User;
use Doctrine\Migrations\DependencyFactory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;

class SetupController extends AbstractController
{
    private DependencyFactory $migrationFactory;

    public function __construct(DependencyFactory $migrationFactory)
    {
        $this->migrationFactory = $migrationFactory;
    }

    #[Route('/run-setup', name: 'run_setup')]
    public function setup(
        EntityManagerInterface $em,
        UserPasswordHasherInterface $passwordHasher
    ): Response {
        $output = "== SETUP START ==\n\n";

        // ---- 1) EXÉCUTER LES MIGRATIONS SANS CONSOLE / STDIN ----
        $output .= "== RUNNING MIGRATIONS ==\n";

        try {
            $aliasResolver   = $this->migrationFactory->getVersionAliasResolver();
            $planCalculator  = $this->migrationFactory->getMigrationPlanCalculator();
            $latestVersion   = $aliasResolver->resolveVersionAlias('latest');
            $plan            = $planCalculator->getPlanUntilVersion($latestVersion);
            $migrator        = $this->migrationFactory->getMigrator();

            $migrator->migrate($plan);

            $output .= "Migrations executed successfully.\n\n";
        } catch (\Throwable $e) {
            return new Response(
                "<pre>Migration error:\n" . $e->getMessage() . "</pre>",
                500
            );
        }

        // ---- 2) CRÉER L’ADMIN SI BESOIN ----
        $output .= "== CREATING ADMIN USER ==\n";

        $existing = $em->getRepository(User::class)->findOneBy([
            'email' => 'admin@test.com'
        ]);

        if (!$existing) {
            $user = new User();
            $user->setEmail('admin@test.com');
            $user->setFirstname('Admin');
            $user->setLastname('TechNova');
            $user->setRoles(['ROLE_ADMIN']);

            $hashed = $passwordHasher->hashPassword($user, "123456");
            $user->setPassword($hashed);

            $em->persist($user);
            $em->flush();

            $output .= "Admin user created successfully.\n";
        } else {
            $output .= "Admin user already exists.\n";
        }

        return new Response("<pre>$output</pre>");
    }
}
