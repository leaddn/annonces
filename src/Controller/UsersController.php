<?php

namespace App\Controller;

use App\Entity\Annonces;
use App\Entity\Images;
use App\Form\AnnoncesType;
use App\Form\EditProfileType;
use App\Service\ManagePicturesService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;

class UsersController extends AbstractController
{
    /**
     * @Route("/users", name="users")
     */
    public function index(): Response
    {
        return $this->render('users/index.html.twig');
    }

    /**
     * @Route("/users/annonces/ajout", name="users_annonces_ajout")
     */
    public function ajoutAnnonce(Request $request)
    {
        $annonce = new Annonces;
        $form = $this->createForm(AnnoncesType::class, $annonce);
        $form->handleRequest($request);

        if($form->isSubmitted() && $form->isValid())
        {
            $annonce->setUsers($this->getUser());
            $annonce->setActive(false);

            // On récupère les images transmises
            $images = $form->get('images')->getData();
            
            foreach ($images as $image) {
                # nouveau nom de fichier
                $fichier = md5(uniqid()) . '.' . $image->guessExtension();

                # copie le fichier dans uploads
                $image->move(
                    $this->getParameter('images_directory'),
                    $fichier
                );

                # stocke le nom de l'image dans bdd
                $img = new Images();
                $img->setName($fichier);
                $annonce->addImage($img);
            }

            // On ajoute les images
            //$picturesService->add($images, $annonce);

            $em = $this->getDoctrine()->getManager();
            $em->persist($annonce);
            $em->flush();

            return $this->redirectToRoute('users');
        }
        return $this->render('users/annonces/ajout.html.twig', [
            'form' => $form->createView(),
        ]);

        
    }

    /**
     * @Route("/users/annonces/edit/{id}", name="users_annonces_edit")
     */
    public function editAnnonce(Request $request, Annonces $annonce)
    {
        //$this->denyAccessUnlessGranted('annonce_edit', $annonce);
        $form = $this->createForm(AnnoncesType::class, $annonce);

        $form->handleRequest($request);

        if($form->isSubmitted() && $form->isValid()){
            $annonce->setActive(false);
            // On récupère les images transmises
            $images = $form->get('images')->getData();
             
            foreach ($images as $image) {
                # nouveau nom de fichier
                $fichier = md5(uniqid()) . '.' . $image->guessExtension();

                # copie le fichier dans uploads
                $image->move(
                    $this->getParameter('images_directory'),
                    $fichier
                );

                # stocke le nom de l'image dans bdd
                $img = new Images();
                $img->setName($fichier);
                $annonce->addImage($img);
            }

            // On ajoute les images
            //$picturesService->add($images, $annonce);

            $em = $this->getDoctrine()->getManager();
            $em->persist($annonce);
            $em->flush();

            return $this->redirectToRoute('users');
        }

        return $this->render('users/annonces/ajout.html.twig', [
            'form' => $form->createView(),
            'annonce' => $annonce
        ]);
    }

    /**
     * @Route("/users/profil/modifier", name="users_profil_modifier")
     */
    public function editProfile(Request $request)
    {
        $user = $this->getUser();
        $form = $this->createForm(EditProfileType::class, $user);
        $form->handleRequest($request);

        if($form->isSubmitted() && $form->isValid())
        {

            $em = $this->getDoctrine()->getManager();
            $em->persist($user);
            $em->flush();

            $this->addFlash('message', 'Profil mis à jour');
            return $this->redirectToRoute('users');
        }
        return $this->render('users/editprofile.html.twig', [
            'form' => $form->createView(),
        ]);

        
    }

            /**
     * @Route("/users/pass/modifier", name="users_pass_modifier")
     */
    public function editPass(Request $request, UserPasswordEncoderInterface $passwordEncoder)
    {   
        if($request->isMethod('POST'))
        {
            $em = $this->getDoctrine()->getManager();
            $user = $this->getUser();
            // On vérifie si les mots de passe sont identiques
            if($request->request->get('pass') == $request->request->get('pass2')){
                $user->setPassword($passwordEncoder->encodePassword($user, $request->request->get('pass')));
                $em->flush();
                $this->addFlash('message', 'Mot de passe mis à jour avec succès');

                return $this->redirectToRoute('users');
            }
            else {
                $this->addFlash('error', 'Les deux mots de passe ne sont pas identiques');
            }
        }
        return $this->render('users/editpass.html.twig');

        
    }

    /**
     * @Route("/supprime/image/{id}", name="annonces_delete_image", methods={"DELETE"})
     *
     */
    public function deleteImage(Images $image, Request $request){
        $data = json_decode($request->getContent(), true);
        
        // vérification validité du token
        if($this->isCsrfTokenValid('delete'.$image->getId(), $data['_token'])){
            // récupère le nom de l'image
            $nom = $image->getName();
            // supprime le fichier dans la bdd
            unlink($this->getParameter('images_directory').'/'.$nom);

            $em=$this->getDoctrine()->getManager();
            $em->remove($image);
            $em->flush();

            // réponse en json
            return new JsonResponse(['success' => 1]);
        }
        else {
            return new JsonResponse(['error' => 'Token invalide'], 400);
            
        }
    }
}
