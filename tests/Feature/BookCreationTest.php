<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookCreationTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_create_a_book_with_valid_data(): void
    {
        $user = User::factory()->create();

        $payload = [
            'title' => 'Arsène Lupin, gentleman cambrioleur',
            'author' => 'Maurice Leblanc',
            'summary' => "Recueil de nouvelles présentant le célèbre voleur gentleman créé par Maurice Leblanc.",
            'isbn' => '9782253003908',
        ];

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/books', $payload);

        $response->assertStatus(201);

        $this->assertDatabaseHas('books', [
            'title' => 'Arsène Lupin, gentleman cambrioleur',
            'author' => 'Maurice Leblanc',
            'isbn' => '9782253003908',
        ]);
    }

    public function test_book_is_not_created_with_invalid_data(): void
    {
        $user = User::factory()->create();

        $payload = [
            'title' => 'XY',
            'author' => 'Bob Rotella',
            'summary' => 'Livre de psychologie sportive appliqué au golf.',
            'isbn' => '9780684803647',
        ];

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/books', $payload);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('title');

        $this->assertDatabaseMissing('books', [
            'isbn' => '9780684803647',
        ]);
        $this->assertSame(0, Book::count());
    }

    public function test_unauthenticated_user_cannot_create_a_book(): void
    {
        $payload = [
            'title' => 'Golf is Not a Game of Perfect',
            'author' => 'Bob Rotella',
            'summary' => 'Approche mentale du golf par un psychologue du sport reconnu.',
            'isbn' => '9780684803647',
        ];

        $response = $this->postJson('/api/v1/books', $payload);

        $response->assertStatus(401);

        $this->assertSame(0, Book::count());
    }
}
