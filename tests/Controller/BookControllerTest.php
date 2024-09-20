<?php

namespace App\Tests\Controller;

use App\Entity\Book;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

class BookControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private $entityManager;
    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->entityManager = $this->client->getContainer()->get(EntityManagerInterface::class);
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
}