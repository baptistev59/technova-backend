<?php

namespace App\Controller;

use Doctrine\Migrations\Tools\Console\Command\MigrateCommand;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Migrations\DependencyFactory;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use App\Entity\User;

class SetupController extends AbstractController
{
    #[Route('/run-setup', name: 'run_setup')]
    public function setup(
        EntityManagerInterface $em,
        UserPasswordHasherInterface $passwordHasher
    ): Response {

        $output = "";

        // ---- 1) Récupérer manuellement le DependencyFactory ----
        /** @var DependencyFactory $factory */
        $factory = $this->container->get('doctrine.migrations.dependency_factory');

        $output .= "== EXECUTING MIGRATIONS ==\n\n";

        // ---- 2) Exécuter la migration ----
        $command = new MigrateCommand($factory);
        $input = new ArrayInput(['--no-interaction' => true]);
        $buffer = new BufferedOutput();
        $command->run($input, $buffer);

        $output .= $buffer->fetch();
        $output .= "\n\nMigrations executed.\n\n";

        // ---- 3) Créer l'utilisateur admin ----
        $output .= "== CREATING ADMIN USER ==\n\n";

        $existing = $em->getRepository(User::class)->findOneBy(['email' => 'admin@test.com']);

        if (!$existing) {
            $user = new User();
            $user->setEmail('admin@test.com');
            $user->setFirstname('Admin');
            $user->setLastname('TechNova');
            $user->setRoles(['ROLE_ADMIN']);

            $hashedPassword = $passwordHasher->hashPassword($user, '123456');
            $user->setPassword($hashedPassword);

            $em->persist($user);
            $em->flush();

            $output .= "Admin user created with email admin@test.com and password 123456\n";
        } else {
            $output .= "Admin user already exists.\n";
        }

        return new Response("<pre>".$output."</pre>");
    }
}
