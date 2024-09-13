<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Book;
use App\Repository\BookRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/books', name: 'create_book')]
class BookController extends AbstractController
{
    public function __construct(private BookRepository $bookRepository, private EntityManagerInterface $entityManager)
    {
    }

    /**
     * @throws Exception
     */
    #[Route('', name: 'book_create', methods: ['POST'])]
    #[OA\Post(
        summary: "Добавление новой книги",
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "title", type: "string", example: "Новая книга"),
                    new OA\Property(property: "publishedAt", type: "string", format: "date", example: "2023-01-01")
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: "Книга успешно создана",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Created successfully'),
                    ]
                )
            ),
            new OA\Response(
                response: 400,
                description: "Ошибка валидации",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'error', type: 'string', example: 'Invalid data')
                    ]
                )
            )
        ]
    )]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        // Валидируем данные
        if (!isset($data['title'])) {
            return $this->json(['error' => 'Invalid data'], Response::HTTP_BAD_REQUEST);
        }

        // Создание новой книги
        $book = new Book();
        $book->setTitle($data['title']);
        $book->setPublishedAt(new DateTimeImmutable());

        $this->entityManager->persist($book);
        $this->entityManager->flush();

        return $this->json(['message' => 'Created successfully'], Response::HTTP_CREATED);
    }
}
