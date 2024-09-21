<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Book;
use App\Repository\BookRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Nelmio\ApiDocBundle\Annotation\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/books', name: 'create_book')]
class BookController extends AbstractController
{
    public function __construct(private readonly BookRepository $bookRepository, private readonly EntityManagerInterface $entityManager)
    {
    }

    #[Route('', name: 'book_list', methods: ['GET'])]
    #[OA\Get(
        summary: 'Получить список всех книг',
        responses: [
            new OA\Response(
                response: 200,
                description: 'Список книг',
                content: new OA\JsonContent(
                    type: 'array',
                    items: new OA\Items(ref: new Model(type: Book::class))
                )
            )
        ]
    )]
    public function list(): JsonResponse
    {
        # TODO Добавить фильтрацию и пагинацию
        $books = $this->bookRepository->findAll();
        return $this->json($books, Response::HTTP_OK);
    }

    #[Route('/{id}', name: 'book_get', methods: ['GET'])]
    #[OA\Get(
        summary: 'Получить книгу по ID',
        responses: [
            new OA\Response(
                response: 200,
                description: 'Информация о книге',
                content: new OA\JsonContent(ref: new Model(type: Book::class))
            ),
            new OA\Response(
                response: 404,
                description: 'Книга не найдена'
            )
        ]
    )]
    public function get(int $id): JsonResponse
    {
        $book = $this->bookRepository->find($id);
        if (null === $book) {
            return $this->json(['error' => 'Книга не найдена'], Response::HTTP_NOT_FOUND);
        }

        return $this->json($book, Response::HTTP_OK);
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
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: "Книга успешно создана",
                content: new OA\JsonContent(ref: new Model(type: Book::class))
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

        return $this->json($book, Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'book_delete', methods: ['DELETE'])]
    #[OA\Delete(
        summary: 'Удалить книгу',
        responses: [
            new OA\Response(
                response: 204,
                description: 'Книга успешно удалена'
            ),
            new OA\Response(
                response: 404,
                description: 'Книга не найдена'
            )
        ]
    )]
    public function delete(int $id): JsonResponse
    {
        $book = $this->bookRepository->find($id);
        if (null === $book) {
            return $this->json(['error' => 'Книга не найдена'], Response::HTTP_NOT_FOUND);
        }

        $this->entityManager->remove($book);
        $this->entityManager->flush();

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }

    #[Route('/{id}', name: 'book_patch', methods: ['PATCH'])]
    #[OA\Patch(
        summary: 'Обновление книги',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "title", type: "string", example: "Частично обновленная книга")
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Книга успешно обновлена',
                content: new OA\JsonContent(ref: new Model(type: Book::class))
            ),
            new OA\Response(
                response: 404,
                description: 'Книга не найдена'
            )
        ]
    )]
    public function patch(Request $request, int $id): JsonResponse
    {
        $book = $this->bookRepository->find($id);
        if (null === $book) {
            return $this->json([['error' => 'Книга не найдена']], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true);
        if (isset($data['title'])) {
            $book->setTitle($data['title']);
        }

        $this->entityManager->persist($book);
        $this->entityManager->flush();

        return $this->json($book, Response::HTTP_OK);
    }

    #[Route('/upload/{id}', name: 'book_upload', methods: ['POST'])]
    #[OA\Post(
        description: 'Загружает файл книги и привязывает его к сущности Book с id из запроса',
        summary: 'Загрузка файла книги',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    properties: [
                        new OA\Property(property: 'file', type: 'string', format: 'binary')
                    ],
                    type: 'object'
                )
            )
        ),
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'ID книги',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            )
        ],
        responses: [
            new OA\Response(
                response: 201,
                description: 'Файл успешно загружен',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'File uploaded successfully'),
                        new OA\Property(property: 'file_path', type: 'string', example: '/uploads/books/1/filename.pdf'),
                    ]
                )
            ),
            new OA\Response(
                response: 400,
                description: 'Ошибка при загрузке файла',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'error', type: 'string', example: 'File not provided')
                    ]
                )
            )
        ]
    )]
    public function upload(Request $request, int $id): JsonResponse
    {
        $book = $this->bookRepository->find($id);
        if (null === $book) {
            return $this->json([['error' => 'Книга не найдена']], Response::HTTP_NOT_FOUND);
        }

        $file = $request->files->get('file');

        if (null === $file)
            return $this->json(['error' => 'File not provided'], Response::HTTP_BAD_REQUEST);

        // Определение папки для загрузки файла
        $uploadDirectory = $this->getParameter('kernel.project_dir') . '/public/uploads/books/' . $book->getId();
        if (!is_dir($uploadDirectory)) {
            mkdir($uploadDirectory, 0755, true);
        }

        // Загрузка файла в нужную папку
        $fileName = $book->getId() . '.' . $file->guessExtension();
        $file->move($uploadDirectory, $fileName);

        return $this->json(['message' => 'File uploaded successfully'], Response::HTTP_CREATED);
    }

    #[Route('/download/{id}', name: 'book_download', methods: ['GET'])]
    #[OA\Get(
        description: "Скачивает файл книги указанного типа, привязанный к сущности Book с указанным ID.",
        summary: "Скачивание файла книги",
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'ID книги',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            ),
            new OA\Parameter(
                name: 'fileType',
                description: 'Тип файла книги, например: pdf, epub, mobi',
                in: 'query',
                required: true,
                schema: new OA\Schema(type: 'string', enum: ['pdf', 'epub', 'mobi'])
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Файл успешно скачан',
                content: new OA\MediaType(
                    mediaType: 'application/octet-stream',
                    schema: new OA\Schema(type: 'string', format: 'binary')
                ),
                headers: [
                    new OA\Header(
                        header: 'Content-Disposition',
                        description: 'Информация о вложении для загрузки файла',
                        schema: new OA\Schema(type: 'string')
                    )
                ]
            ),
            new OA\Response(
                response: 400,
                description: 'Ошибка запроса: тип файла не указан или файл не найден',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'error', type: 'string', example: 'File type not provided')
                    ]
                )
            ),
            new OA\Response(
                response: 404,
                description: 'Файл не найден',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'error', type: 'string', example: 'File not found')
                    ]
                )
            )
        ]
    )]
    public function download(int $id, Request $request): Response
    {
        $book = $this->bookRepository->find($id);
        if (null === $book) {
            return $this->json([['error' => 'Книга не найдена']], Response::HTTP_NOT_FOUND);
        }

        // Получение типа файла из запроса (например, pdf, epub, mobi)
        $fileType = $request->query->get('fileType');
        if (null === $fileType)
            return $this->json(['error' => 'File type not provided'], Response::HTTP_BAD_REQUEST);

        // Определение пути к файлу книги
        $filePath = $this->getParameter('kernel.project_dir') . "/public/uploads/books/{$book->getId()}/{$book->getId()}.$fileType";
        if (!file_exists($filePath))
            return $this->json(['error' => 'File not found'], Response::HTTP_BAD_REQUEST);

        // Возвращаем файл в ответе
        return $this->file($filePath);
    }
}
