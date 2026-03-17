<?php

namespace App\Controller;

use App\Entity\Artist;
use App\Entity\Card;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use OpenApi\Attributes as OA;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/card', name: 'api_card_')]
#[OA\Tag(name: 'Card', description: 'Routes for all about cards')]
class ApiCardController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger
    ) {
    }
    #[Route('/all', name: 'List all cards', methods: ['GET'])]
    #[OA\Put(description: 'Return all cards in the database')]
    #[OA\Response(response: 200, description: 'List all cards')]
    public function cardAll(): Response
    {
        $cards = $this->entityManager->getRepository(Card::class)->findAll();
        return $this->json($cards);
    }

    #[Route('/search', name: 'search', methods: ['GET'])]
    #[OA\Get(description: 'Search cards by name (minimum 3 characters)')]
    #[OA\Response(response: 200, description: 'List of matching cards (max 20 results)')]
    #[OA\Response(response: 400, description: 'Query too short')]
    public function searchCard(Request $request): Response
    {
        $query = $request->query->get('q', '');

        if (strlen(trim($query)) < 3) {
            return $this->json(['error' => 'la requête doit contenir au moins 3 caracters'], 400);
        }

        $cards = $this->entityManager->getRepository(Card::class)->getByName($query, 20);
        return $this->json($cards);
    }

    #[Route('/{uuid}', name: 'Show card', methods: ['GET'])]
    #[OA\Parameter(name: 'uuid', description: 'UUID of the card', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\Put(description: 'Get a card by UUID')]
    #[OA\Response(response: 200, description: 'Show card')]
    #[OA\Response(response: 404, description: 'Card not found')]
    public function cardShow(string $uuid): Response
    {
        $card = $this->entityManager->getRepository(Card::class)->findOneBy(['uuid' => $uuid]);
        if (!$card) {
            return $this->json(['error' => 'Card not found'], 404);
        }
        return $this->json($card);
    }
}
