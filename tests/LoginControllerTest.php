<?php

namespace App\Tests;

use App\Entity\Client;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class LoginControllerTest extends WebTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $container = static::getContainer();
        $em = $container->get('doctrine.orm.entity_manager');
        $userRepository = $em->getRepository(Client::class);

        // Remove any existing users from the test database
        foreach ($userRepository->findAll() as $user) {
            $em->remove($user);
        }

        $em->flush();

        // Create a Client fixture
        /** @var UserPasswordHasherInterface $passwordHasher */
        $passwordHasher = $container->get('security.user_password_hasher');

        $user = (new Client())
            ->setEmail('email@example.com')
            ->setNom('Test')
            ->setPrenom('User')
            ->setTelephone('0123456789')
            ->setDateCreation(new \DateTimeImmutable());
        $user->setPassword($passwordHasher->hashPassword($user, 'password'));
        $user->setRole('ROLE_CLIENT');

        $em->persist($user);
        $em->flush();
    }

    public function testLogin(): void
    {
        // Denied - Can't login with invalid email address.
        $this->client->request('GET', '/login/client');
        self::assertResponseIsSuccessful();

        $this->client->submitForm('Se connecter', [
            '_username' => 'doesNotExist@example.com',
            '_password' => 'password',
        ]);

        self::assertResponseRedirects('/login/client');
        $this->client->followRedirect();

        // Ensure we do not reveal if the user exists or not.
        self::assertSelectorTextContains('.alert-danger', 'Invalid credentials.');

        // Denied - Can't login with invalid password.
        $this->client->request('GET', '/login/client');
        self::assertResponseIsSuccessful();

        $this->client->submitForm('Se connecter', [
            '_username' => 'email@example.com',
            '_password' => 'bad-password',
        ]);

        self::assertResponseRedirects('/login/client');
        $this->client->followRedirect();

        // Ensure we do not reveal the user exists but the password is wrong.
        self::assertSelectorTextContains('.alert-danger', 'Invalid credentials.');

        // Success - Login with valid credentials is allowed.
        $this->client->submitForm('Se connecter', [
            '_username' => 'email@example.com',
            '_password' => 'password',
        ]);

        self::assertResponseRedirects('/client/dashboard');
        $this->client->followRedirect();

        self::assertSelectorNotExists('.alert-danger');
    }
}
