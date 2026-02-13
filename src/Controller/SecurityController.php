<?php


namespace App\Controller;

use App\Entity\User;
use App\Form\RegistrationFormType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class SecurityController extends AbstractController
{
  
    #[Route('/register', name: 'app_register')]
    public function register(
        Request $request,
        UserPasswordHasherInterface $userPasswordHasher,
        EntityManagerInterface $entityManager
    ): Response {
        // Si l'utilisateur est déjà connecté, on le redirige vers l'accueil
        if ($this->getUser()) {
            return $this->redirectToRoute('home');
        }

        // Création d'un nouvel objet User vide
        $user = new User();
        
        // Création du formulaire d'inscription
        // On lui passe l'objet User pour qu'il remplisse ses propriétés
        $form = $this->createForm(RegistrationFormType::class, $user);
        
        // handleRequest analyse la requête HTTP
        // Si le formulaire a été soumis, il remplit automatiquement l'objet $user
        $form->handleRequest($request);

        // Vérifie si le formulaire a été soumis ET est valide
        if ($form->isSubmitted() && $form->isValid()) {
            /** @var string $plainPassword */
            // On récupère le mot de passe en clair depuis le formulaire
            $plainPassword = $form->get('plainPassword')->getData();

            // IMPORTANT : On ne stocke JAMAIS le mot de passe en clair !
            // hashPassword() le transforme en chaîne cryptée
            $user->setPassword($userPasswordHasher->hashPassword($user, $plainPassword));

            // On prépare l'insertion en base de données
            $entityManager->persist($user);
            // On exécute réellement l'insertion
            $entityManager->flush();

            // Message de confirmation pour l'utilisateur
            $this->addFlash('success', 'Votre compte a été créé avec succès ! Vous pouvez maintenant vous connecter.');

            // Redirection vers la page de connexion
            return $this->redirectToRoute('app_login');
        }

        // Affichage de la page avec le formulaire
        // 'render' charge un template Twig et y passe des variables
        return $this->render('security/register.html.twig', [
            'registrationForm' => $form,
        ]);
    }

  
    #[Route('/login', name: 'app_login')]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        // Si déjà connecté, redirection vers l'accueil
        if ($this->getUser()) {
            return $this->redirectToRoute('home');
        }

        // Récupère l'erreur de connexion s'il y en a une
        // Exemple : "Identifiants invalides"
        $error = $authenticationUtils->getLastAuthenticationError();

        // Récupère le dernier email saisi (pour le pré-remplir en cas d'erreur)
        $lastUsername = $authenticationUtils->getLastUsername();

        return $this->render('security/login.html.twig', [
            'last_username' => $lastUsername,
            'error' => $error,
        ]);
    }

   
    #[Route('/logout', name: 'app_logout')]
    public function logout(): void
    {
        // Cette méthode peut rester vide
        // Symfony gère tout automatiquement
        throw new \LogicException('This method can be blank - it will be intercepted by the logout key on your firewall.');
    }
}
