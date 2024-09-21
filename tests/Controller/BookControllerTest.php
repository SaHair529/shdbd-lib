<?php

namespace App\Tests\Controller;

use App\Entity\Book;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Response;

class BookControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private ?EntityManagerInterface $entityManager;
    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->entityManager = $this->client->getContainer()->get(EntityManagerInterface::class);

        // Очистка таблицы книг перед каждым тестом
        $this->entityManager->createQuery('DELETE FROM App\Entity\Book')->execute();
    }

    public function testListBooksSuccessfully(): void
    {
        // Предварительно добавляем несколько книг в базу данных
        $book1 = new Book();
        $book1->setTitle('Book 1 from list test');
        $book1->setPublishedAt(new DateTimeImmutable());

        $book2 = new Book();
        $book2->setTitle('Book 2 from list test');
        $book2->setPublishedAt(new DateTimeImmutable());

        $this->entityManager->persist($book1);
        $this->entityManager->persist($book2);
        $this->entityManager->flush();

        $this->client->request('GET', '/api/books');

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);

        $responseContent = $this->client->getResponse()->getContent();
        $responseData = json_decode($responseContent, true);

        $this->assertIsArray($responseData);
        $this->assertCount(2, $responseData);

        $this->assertEquals('Book 1 from list test', $responseData[0]['title']);
        $this->assertEquals('Book 2 from list test', $responseData[1]['title']);
    }

    public function testGetBookSuccessfully(): void
    {
        $book = new Book();
        $book->setTitle('Тестовая книга');
        $book->setPublishedAt(new DateTimeImmutable());

        $this->entityManager->persist($book);
        $this->entityManager->flush();

        $this->client->request('GET', '/api/books/' . $book->getId());
        $this->assertResponseStatusCodeSame(Response::HTTP_OK);

        $responseContent = $this->client->getResponse()->getContent();
        $responseData = json_decode($responseContent, true);

        $this->assertEquals($book->getTitle(), $responseData['title']);
        $this->assertEquals($book->getPublishedAt()->format('Y-m-d\TH:i:sP'), $responseData['publishedAt']);
    }

    public function testGetBookNotFound(): void
    {
        $this->client->request('GET', '/api/books/999999'); // Предполагаем, что книги с таким ID нет

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);

        $responseContent = $this->client->getResponse()->getContent();
        $responseData = json_decode($responseContent, true);

        $this->assertEquals('Книга не найдена', $responseData['error']);
    }

    public function testCreateBookSuccessfully(): void
    {
        $data = ['title' => 'Тестовая книга'];

        $this->client->request('POST', '/api/books', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode($data));

        $responseContent = $this->client->getResponse()->getContent();
        $responseData = json_decode($responseContent, true);

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $this->assertArrayHasKey('id', $responseData);
        $this->assertArrayHasKey('title', $responseData);
        $this->assertArrayHasKey('publishedAt', $responseData);
        $this->assertEquals($data['title'], $responseData['title']);

        $book = $this->entityManager->getRepository(Book::class)->findOneBy(['title' => $data['title']]);
        $this->assertnotnull($book);
    }

    public function testCreateBookFailsWithMissingTitle(): void
    {
        $data = [];

        $this->client->request('POST', '/api/books', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode($data));

        $this->assertresponseStatusCodeSame(Response::HTTP_BAD_REQUEST);

        $responseContent = $this->client->getResponse()->getContent();
        $responseData = json_decode($responseContent, true);

        $this->assertArrayHasKey('error', $responseData);
        $this->assertEquals('Invalid data', $responseData['error']);
    }

    public function testDeleteBookSuccessfully(): void
    {
        $book = new Book();
        $book->setTitle('Тестовая книга');
        $book->setPublishedAt(new DateTimeImmutable());

        $this->entityManager->persist($book);
        $this->entityManager->flush();
        $deletedBookId = $book->getId();

        $this->client->request('DELETE', '/api/books/' . $book->getId());

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        $deletedBook = $this->entityManager->getRepository(Book::class)->find($deletedBookId);
        $this->assertNull($deletedBook);
    }

    public function testDeleteBookNotFound(): void
    {
        $this->client->request('DELETE', '/api/books/999999'); // Предполагаем, что книги с таким ID нет

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);

        $responseContent = $this->client->getResponse()->getContent();
        $responseData = json_decode($responseContent, true);

        $this->assertEquals('Книга не найдена', $responseData['error']);
    }

    public function testPatchBookSuccessfully(): void
    {
        $book = new Book();
        $book->setTitle('Старая книга');
        $book->setPublishedAt(new DateTimeImmutable());

        $this->entityManager->persist($book);
        $this->entityManager->flush();

        $this->client->request('PATCH', '/api/books/' . $book->getId(), [], [], ['CONTENT_TYPE' => 'application/json'], json_encode(['title' => 'Обновленная книга']));

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);

        $updatedBook = $this->entityManager->getRepository(Book::class)->find($book->getId());
        $this->assertEquals('Обновленная книга', $updatedBook->getTitle());
    }

    public function testPatchBookNotFound(): void
    {
        $this->client->request('PATCH', '/api/books/999999', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode(['title' => 'Неизвестная книга']));

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);

        $responseContent = $this->client->getResponse()->getContent();
        $responseData = json_decode($responseContent, true);

        $this->assertEquals('Книга не найдена', $responseData[0]['error']);
    }

    public function testUploadBookFileSuccessfully(): void
    {
        $book = new Book();
        $book->setTitle('Тестовая книга');
        $book->setPublishedAt(new DateTimeImmutable());

        $this->entityManager->persist($book);
        $this->entityManager->flush();

        $projectDir = $this->client->getContainer()->getParameter('kernel.project_dir');
        $filePath = $projectDir . '/var/tmp/test_file.txt';

        if (!is_dir(dirname($filePath)))
            mkdir(dirname($filePath), 0755, true);

        file_put_contents($filePath, 'Тестовое содержание файла');

        $this->client->request(
            'POST',
            '/api/books/upload/' . $book->getId(),
            [],
            ['file' => new UploadedFile($filePath, 'file.txt', 'application/txt', null, true)]
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $responseData = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertEquals('File uploaded successfully', $responseData['message']);

        $uploadedFilePath = $projectDir.'/public/uploads/books/'.$book->getId().'/'.$book->getId().'.txt';
        $this->assertfileexists($uploadedFilePath);

        rmdir(dirname($filePath));
        unlink($uploadedFilePath);
        rmdir(dirname($uploadedFilePath));
    }

    public function testUploadFileNotProvided(): void
    {
        $book = new Book();
        $book->setTitle('Книга для теста загрузки');
        $book->setPublishedAt(new DateTimeImmutable());
        $this->entityManager->persist($book);
        $this->entityManager->flush();

        // Отправляем POST-запрос без файла
        $this->client->request('POST', '/api/books/upload/' . $book->getId(), [], [], ['CONTENT_TYPE' => 'multipart/form-data']);
        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);

        $responseContent = $this->client->getResponse()->getContent();
        $this->assertJson($responseContent);

        $responseData = json_decode($responseContent, true);
        $this->assertArrayHasKey('error', $responseData);
        $this->assertEquals('File not provided', $responseData['error']);
    }

    public function testUploadFileProvided(): void
    {
        $nonExistentBookId = 9999;

        $projectDir = $this->client->getContainer()->getParameter('kernel.project_dir');
        $filePath = $projectDir . '/var/tmp/test_file.txt';

        if (!is_dir(dirname($filePath)))
            mkdir(dirname($filePath), 0755, true);

        file_put_contents($filePath, 'Тестовое содержание файла');

        $this->client->request(
            'POST',
            '/api/books/upload/' . $nonExistentBookId,
            [],
            ['file' => new UploadedFile($filePath, 'file.txt', 'application/txt', null, true)]
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);

        $responseContent = $this->client->getResponse()->getContent();
        $this->assertJson($responseContent);

        $responseData = json_decode($responseContent, true);
        $this->assertArrayHasKey('error', $responseData);
        $this->assertEquals('Книга не найдена', $responseData['error'] ?? '');
    }

    public function testDownloadFileSuccess(): void
    {
        $book = new Book();
        $book->setTitle('Тестовая книга для скачивания');
        $book->setPublishedAt(new DateTimeImmutable());
        $this->entityManager->persist($book);
        $this->entityManager->flush();

        $uploadDirectory = $this->client->getKernel()->getProjectDir() . "/public/uploads/books/{$book->getId()}";
        mkdir($uploadDirectory, 0755, true);
        $filePath = $uploadDirectory . "/{$book->getId()}.txt";
        touch($filePath);
        file_put_contents($filePath, 'Test');

        $this->client->request('GET', '/api/books/download/' . $book->getId() . '?fileType=txt');

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);

        unlink($filePath);
        rmdir($uploadDirectory);
    }

    public function testDownloadNonExistentFile(): void
    {
        // Создаем тестовую книгу
        $book = new Book();
        $book->setTitle('Тестовая книга для отсутствующего файла');
        $book->setPublishedAt(new DateTimeImmutable());
        $this->entityManager->persist($book);
        $this->entityManager->flush();

        // Отправляем GET-запрос для несуществующего файла
        $this->client->request('GET', '/api/books/download/' . $book->getId() . '?fileType=epub');

        // Проверяем, что ответ имеет статус 400 и выведено сообщение об ошибке
        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        $this->assertJsonStringEqualsJsonString(
            json_encode(['error' => 'File not found']),
            $this->client->getResponse()->getContent()
        );
    }

    public function testDownloadFileForNonExistentBook(): void
    {
        $this->client->request('GET', '/api/books/download/999999?fileType=pdf');

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $this->assertJsonStringEqualsJsonString(
            json_encode(['error' => 'Книга не найдена']),
            $this->client->getResponse()->getContent()
        );
    }

}