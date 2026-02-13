<?php
// src/Form/RegistrationFormType.php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\IsTrue;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class RegistrationFormType extends AbstractType
{
    /**
     * Cette méthode construit le formulaire
     * C'est comme construire un formulaire HTML, mais en PHP
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            // Champ EMAIL
            ->add('email', EmailType::class, [
                'label' => 'Adresse email',
                // attr = attributs HTML du champ
                'attr' => [
                    'placeholder' => 'votre@email.com',
                    'class' => 'form-control'
                ],
                // Contraintes de validation
                'constraints' => [
                    new NotBlank([
                        'message' => 'Veuillez saisir une adresse email',
                    ]),
                ],
            ])
            
            // Champ ACCEPTATION DES CGU
            ->add('agreeTerms', CheckboxType::class, [
                'label' => 'J\'accepte les conditions générales d\'utilisation',
                // mapped: false signifie que ce champ ne correspond à aucune propriété de User
                // C'est juste pour la validation du formulaire
                'mapped' => false,
                'constraints' => [
                    new IsTrue([
                        'message' => 'Vous devez accepter les conditions.',
                    ]),
                ],
            ])
            
            // Champ MOT DE PASSE
            // RepeatedType = demande 2 fois le mot de passe pour confirmation
            ->add('plainPassword', RepeatedType::class, [
                'type' => PasswordType::class,
                // mapped: false car on ne stocke PAS le mot de passe en clair
                // On le hashera avant de le mettre dans User
                'mapped' => false,
                
                // Configuration du premier champ
                'first_options' => [
                    'label' => 'Mot de passe',
                    'attr' => [
                        'placeholder' => 'Choisissez un mot de passe',
                        'class' => 'form-control'
                    ],
                    'constraints' => [
                        new NotBlank([
                            'message' => 'Veuillez saisir un mot de passe',
                        ]),
                        new Length([
                            'min' => 6,
                            'minMessage' => 'Votre mot de passe doit contenir au moins {{ limit }} caractères',
                            'max' => 4096, // Limite technique de Symfony
                        ]),
                    ],
                ],
                
                // Configuration du champ de confirmation
                'second_options' => [
                    'label' => 'Confirmer le mot de passe',
                    'attr' => [
                        'placeholder' => 'Confirmez votre mot de passe',
                        'class' => 'form-control'
                    ],
                ],
                
                // Message si les 2 mots de passe ne correspondent pas
                'invalid_message' => 'Les mots de passe doivent être identiques.',
            ]);
    }

    /**
     * Configuration du formulaire
     */
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            // Lie le formulaire à l'entité User
            'data_class' => User::class,
        ]);
    }
}