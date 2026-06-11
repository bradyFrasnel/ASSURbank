<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class TransactionFilterType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('type', ChoiceType::class, [
                'label' => 'Type',
                'required' => false,
                'placeholder' => 'Tous',
                'choices' => ['Débit' => 'débit', 'Crédit' => 'crédit'],
            ])
            ->add('statut', ChoiceType::class, [
                'label' => 'Statut',
                'required' => false,
                'placeholder' => 'Tous',
                'choices' => ['Succès' => 'succès', 'Échoué' => 'échoué'],
            ])
            ->add('libelle', TextType::class, [
                'label' => 'Libellé',
                'required' => false,
                'attr' => ['placeholder' => 'Rechercher…'],
            ])
            ->add('date_debut', DateType::class, [
                'label' => 'Du',
                'required' => false,
                'widget' => 'single_text',
            ])
            ->add('date_fin', DateType::class, [
                'label' => 'Au',
                'required' => false,
                'widget' => 'single_text',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'method' => 'GET',
            'csrf_protection' => false,
        ]);
    }
}
