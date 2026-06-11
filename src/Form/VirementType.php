<?php

namespace App\Form;

use App\Entity\Compte;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\GreaterThan;
use Symfony\Component\Validator\Constraints\NotBlank;

class VirementType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var list<Compte> $comptes */
        $comptes = $options['comptes'];

        $builder
            ->add('compteSource', EntityType::class, [
                'class' => Compte::class,
                'choices' => $comptes,
                'choice_label' => fn (Compte $c) => sprintf('%s — %.2f FCFA', $c->getNumeroCompte(), $c->getSolde()),
                'label' => 'Compte source',
            ])
            ->add('compteDestination', EntityType::class, [
                'class' => Compte::class,
                'choices' => $comptes,
                'choice_label' => fn (Compte $c) => sprintf('%s — %.2f FCFA', $c->getNumeroCompte(), $c->getSolde()),
                'label' => 'Compte destination',
            ])
            ->add('montant', MoneyType::class, [
                'label' => 'Montant',
                'currency' => 'XAF',
                'constraints' => [new NotBlank(), new GreaterThan(0)],
            ])
            ->add('libelle', TextType::class, ['label' => 'Libellé']);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'comptes' => [],
        ]);
        $resolver->setAllowedTypes('comptes', 'array');
    }
}
