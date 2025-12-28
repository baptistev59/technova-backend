<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:create-admin',
    description: 'Crée un utilisateur administrateur (mode interactif)',
)]
/**
 * Commande utilitaire pour rejouer la création d'un admin en local/prod.
 * Utile après un chargement de fixtures ou un reset de base Alwaysdata.
 */
class CreateAdminCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $em,
        private UserPasswordHasherInterface $passwordHasher,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var QuestionHelper $helper */
        $helper = $this->getHelper('question');

        // -------------------------
        // EMAIL
        // -------------------------
        $emailQuestion = new Question("Email de l'admin : ");
        $email = $helper->ask($input, $output, $emailQuestion);

        if (!$email) {
            $output->writeln("<error>L'email est obligatoire.</error>");

            return Command::FAILURE;
        }

        $existing = $this->em->getRepository(User::class)->findOneBy(['email' => $email]);

        if ($existing) {
            $output->writeln('<comment>Un utilisateur avec cet email existe déjà.</comment>');
            $confirm = new ConfirmationQuestion("Voulez-vous l'écraser ? (yes/no) ", false);

            if (!$helper->ask($input, $output, $confirm)) {
                $output->writeln('<info>Opération annulée.</info>');

                return Command::SUCCESS;
            }
        }

        // -------------------------
        // MOT DE PASSE
        // -------------------------
        $passwordQuestion = new Question('Mot de passe admin : ');
        $passwordQuestion->setHidden(true);
        $passwordQuestion->setHiddenFallback(false);

        $password = $helper->ask($input, $output, $passwordQuestion);

        if (!$password) {
            $output->writeln('<error>Le mot de passe est obligatoire.</error>');

            return Command::FAILURE;
        }

        // -------------------------
        // PRENOM
        // -------------------------
        $firstnameQuestion = new Question('Prénom (default: Admin) : ', 'Admin');
        $firstname = $helper->ask($input, $output, $firstnameQuestion);

        // -------------------------
        // NOM
        // -------------------------
        $lastnameQuestion = new Question('Nom (default: TechNova) : ', 'TechNova');
        $lastname = $helper->ask($input, $output, $lastnameQuestion);

        // -------------------------
        // CREATION OU UPDATE
        // -------------------------
        if (!$existing) {
            $user = new User();
        } else {
            $user = $existing;
        }

        $user->setEmail($email);
        $user->setFirstname($firstname);
        $user->setLastname($lastname);
        $user->setRoles(['ROLE_ADMIN']);

        $hash = $this->passwordHasher->hashPassword($user, $password);
        $user->setPassword($hash);

        $this->em->persist($user);
        $this->em->flush();

        $output->writeln('<info>Utilisateur administrateur créé / mis à jour avec succès.</info>');

        return Command::SUCCESS;
    }
}
