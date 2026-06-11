<?php

namespace App\Form;

use App\Entity\Client;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CompteCreateType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var list<Client> $clients */
        $clients = $options['clients'];

        $builder
            ->add('client', EntityType::class, [
                'class' => Client::class,
                'choices' => $clients,
                'choice_label' => fn (Client $c) => sprintf('%s %s (%s)', $c->getPrenom(), $c->getNom(), $c->getEmail()),
                'label' => 'Client',
                'placeholder' => '— Sélectionner un client —',
            ])
            ->add('type', ChoiceType::class, [
                'label' => 'Type de compte',
                'choices' => [
                    'Courant' => 'courant',
                    'Épargne' => 'épargne',
                    'Titre' => 'titre',
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'clients' => [],
        ]);
        $resolver->setAllowedTypes('clients', 'array');
    }
}
