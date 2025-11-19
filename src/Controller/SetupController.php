<?php

namespace App\Controller;

use App\Entity\User;
use Doctrine\Migrations\DependencyFactory;
use Doctrine\Migrations\MigratorConfiguration;
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

        try {
            // ----------------------------------------------------------
            // 1) SYNC METADATA STORAGE
            // ----------------------------------------------------------
            $output .= "== SYNC METADATA STORAGE ==\n";

            $storage = $this->migrationFactory->getMetadataStorage();
            $storage->ensureInitialized();
            $output .= "Metadata storage synced.\n\n";

            // ----------------------------------------------------------
            // 2) EXÉCUTER LES MIGRATIONS
            // ----------------------------------------------------------
            $output .= "== RUNNING MIGRATIONS ==\n";

            $aliasResolver   = $this->migrationFactory->getVersionAliasResolver();
            $planCalculator  = $this->migrationFactory->getMigrationPlanCalculator();
            $latestVersion   = $aliasResolver->resolveVersionAlias('latest');
            $plan            = $planCalculator->getPlanUntilVersion($latestVersion);

            $config = new MigratorConfiguration();
            $config->setDryRun(false);
            $config->setTimeAllQueries(true);

            $migrator = $this->migrationFactory->getMigrator();
            $migrator->migrate($plan, $config);

            $output .= "Migrations executed successfully.\n\n";

        } catch (\Throwable $e) {
            return new Response(
                "<pre>Migration error:\n" . $e->getMessage() . "\n\n" . $e->getTraceAsString() . "</pre>",
                500
            );
        }

        // ----------------------------------------------------------
        // 3) CRÉER L’UTILISATEUR ADMIN SI NON EXISTANT
        // ----------------------------------------------------------
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

            $output .= "Admin user created.\n";
        } else {
            $output .= "Admin user already exists.\n";
        }

        return new Response("<pre>$output\n\n== SETUP DONE ==</pre>");
    }
}
