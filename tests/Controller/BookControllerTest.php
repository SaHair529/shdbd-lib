<?php

namespace App\Tests\Controller;

use App\Entity\Book;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
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

    public function testListSuccessfully(): void
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

    public function testCreateSuccessfully(): void
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

    public function testCreateFailsWithMissingTitle(): void
    {
        $data = [];

        $this->client->request('POST', '/api/books', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode($data));

        $this->assertresponseStatusCodeSame(Response::HTTP_BAD_REQUEST);

        $responseContent = $this->client->getResponse()->getContent();
        $responseData = json_decode($responseContent, true);

        $this->assertArrayHasKey('error', $responseData);
        $this->assertEquals('Invalid data', $responseData['error']);
    }
}