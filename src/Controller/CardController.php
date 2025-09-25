<?php

namespace App\Controller;

use App\Entity\CardAnime;
use App\Entity\UserCardAnime;
use App\Entity\User; // N'oubliez pas d'importer l'entité User si vous en avez une
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class CardController extends AbstractController
{
    // C'est un exemple de route pour obtenir une carte.
    // Vous pouvez l'adapter à la manière dont les joueurs obtiennent des cartes.
    #[Route('/gain-card/{id}', name: 'app_gain_card')]
    #[IsGranted('ROLE_USER')] // Assurez-vous que seul un utilisateur connecté peut accéder à cette route
    public function gainCard(EntityManagerInterface $entityManager, CardAnime $card): Response
    {
        // Récupérer l'utilisateur actuellement connecté
        $user = $this->getUser();

        // 1. Récupérer le stock de la carte spécifique
        $maxStock = $card->getQuantity();
    
        // 2. Compter le nombre de fois que cette carte a déjà été distribuée
        $totalDistributed = $entityManager->getRepository(UserCardAnime::class)
            ->createQueryBuilder('uca')
            ->select('SUM(uca.quantity)')
            ->where('uca.cardAnime = :card')
            ->setParameter('card', $card)
            ->getQuery()
            ->getSingleScalarResult();
    
        // 3. Vérifier s'il reste du stock
        if ($totalDistributed >= $maxStock) {
            return new Response("Désolé, cette carte est en rupture de stock.");
        }
    
        // 4. Ajouter la carte à la collection du joueur
        $userCard = $entityManager->getRepository(UserCardAnime::class)->findOneBy([
            'user' => $user,
            'cardAnime' => $card,
        ]);

        if ($userCard) {
            // Si l'utilisateur possède déjà la carte, augmentez la quantité
            $userCard->setQuantity($userCard->getQuantity() + 1);
        } else {
            // Si l'utilisateur ne possède pas encore la carte, créez une nouvelle entrée
            $userCard = new UserCardAnime();
            $userCard->setUser($user);
            $userCard->setCardAnime($card);
            $userCard->setQuantity(1);
            $entityManager->persist($userCard);
        }
        
        $entityManager->flush();
    
        return new Response("Félicitations, vous avez obtenu une nouvelle carte !");
    }
}