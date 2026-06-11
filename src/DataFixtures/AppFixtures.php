<?php

namespace App\DataFixtures;

use App\Entity\Banque;
use App\Entity\Client;
use App\Entity\Compte;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{
    private UserPasswordHasherInterface $passwordHasher;

    // On injecte le service de hachage des mots de passe
    public function __construct(UserPasswordHasherInterface $passwordHasher)
    {
        $this->passwordHasher = $passwordHasher;
    }

    public function load(ObjectManager $manager): void
    {
        $dateNow = new \DateTimeImmutable();

        // 1. CRÉATION DE L'ADMINISTRATEUR SUPRÊME
        $admin = new Client();
        $admin->setEmail('admin@system.fr');
        $admin->setNom('Système');
        $admin->setPrenom('Admin');
        $admin->setTelephone('+24101000000');
        $admin->setStatut('actif');
        $admin->setDateCreation($dateNow);
        $admin->setRole('ROLE_ADMIN');

        $hashedPassword = $this->passwordHasher->hashPassword($admin, 'Admin123!');
        $admin->setPassword($hashedPassword);

        $manager->persist($admin);

        // 2. CRÉATION D'UNE BANQUE DE TEST
        $banque = new Banque();
        $banque->setNom('Banque Test');
        $banque->setEmail('banque@test.fr');
        $banque->setTelephone('+24101777777');
        $banque->setStatut('actif');
        $banque->setDateCreation($dateNow);
        $banque->setRole('ROLE_BANQUE');

        $hashedPasswordBanque = $this->passwordHasher->hashPassword($admin, 'Banque123!');
        $banque->setPassword($hashedPasswordBanque);

        $manager->persist($banque);

        // 3. CRÉATION D'UN CLIENT DE TEST
        $client = new Client();
        $client->setEmail('client@test.fr');
        $client->setNom('Dupont');
        $client->setPrenom('Jean');
        $client->setTelephone('+24101234567');
        $client->setStatut('actif');
        $client->setDateCreation($dateNow);
        $client->setRole('ROLE_CLIENT');
        $client->setBanque($banque);

        $hashedPasswordClient = $this->passwordHasher->hashPassword($client, 'Client123!');
        $client->setPassword($hashedPasswordClient);

        $manager->persist($client);

        // 4. CRÉATION D'UN COMPTE POUR LE CLIENT
        $compte = new Compte();
        $compte->setNumeroCompte('FR1234567890123456789012345');
        $compte->setType('courant');
        $compte->setSolde(1000.00);
        $compte->setStatut('actif');
        $compte->setDateCreation($dateNow);
        $compte->setClient($client);

        $manager->persist($compte);

        $manager->flush();
    }
}