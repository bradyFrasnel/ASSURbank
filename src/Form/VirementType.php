<?php

namespace App\Form;

use App\Entity\Compte;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class VirementType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('compteSource', EntityType::class, [
                'class' => Compte::class,
                'choice_label' => 'numeroCompte',
                'label' => 'Compte source',
                'attr' => ['class' => 'form-control']
            ])
            ->add('compteDestination', EntityType::class, [
                'class' => Compte::class,
                'choice_label' => 'numeroCompte',
                'label' => 'Compte destination',
                'attr' => ['class' => 'form-control']
            ])
            ->add('montant', NumberType::class, [
                'label' => 'Montant',
                'scale' => 2,
                'attr' => [
                    'class' => 'form-control',
                    'step' => '0.01',
                    'min' => '0.01'
                ]
            ])
            ->add('libelle', TextType::class, [
                'label' => 'Libellé',
                'attr' => ['class' => 'form-control']
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            // Configure your form options here
        ]);
    }
}
