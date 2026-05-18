<?php

namespace App\Http\Controllers;

use App\Http\Resources\BookResource;
use App\Models\Book;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Cache;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'Book',
    type: 'object',
    properties: [
        new OA\Property(property: 'title', type: 'string', example: '1984'),
        new OA\Property(property: 'author', type: 'string', description: 'Renvoyé en majuscules', example: 'GEORGE ORWELL'),
        new OA\Property(property: 'summary', type: 'string', example: 'Roman dystopique culte...'),
        new OA\Property(property: 'isbn', type: 'string', example: '9780451524935'),
        new OA\Property(property: '_links', type: 'object', properties: [
            new OA\Property(property: 'self', type: 'string', example: 'http://127.0.0.1:8000/api/v1/books/1'),
            new OA\Property(property: 'update', type: 'string', example: 'http://127.0.0.1:8000/api/v1/books/1'),
            new OA\Property(property: 'delete', type: 'string', example: 'http://127.0.0.1:8000/api/v1/books/1'),
            new OA\Property(property: 'all', type: 'string', example: 'http://127.0.0.1:8000/api/v1/books'),
        ]),
    ]
)]
class BookController extends Controller
{
    #[OA\Get(
        path: '/books',
        summary: 'Liste paginée des livres',
        description: 'Retourne la liste des livres paginée (2 par page).',
        tags: ['Books'],
        parameters: [
            new OA\Parameter(ref: '#/components/parameters/AcceptJsonHeader'),
            new OA\Parameter(name: 'page', description: 'Numéro de page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', example: 1)),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Liste paginée',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/Book')),
                        new OA\Property(property: 'links', type: 'object', properties: [
                            new OA\Property(property: 'first', type: 'string', example: 'http://127.0.0.1:8000/api/v1/books?page=1'),
                            new OA\Property(property: 'last', type: 'string', example: 'http://127.0.0.1:8000/api/v1/books?page=2'),
                            new OA\Property(property: 'prev', type: 'string', nullable: true, example: null),
                            new OA\Property(property: 'next', type: 'string', nullable: true, example: 'http://127.0.0.1:8000/api/v1/books?page=2'),
                        ]),
                        new OA\Property(property: 'meta', type: 'object', properties: [
                            new OA\Property(property: 'current_page', type: 'integer', example: 1),
                            new OA\Property(property: 'last_page', type: 'integer', example: 2),
                            new OA\Property(property: 'per_page', type: 'integer', example: 2),
                            new OA\Property(property: 'total', type: 'integer', example: 4),
                        ]),
                    ]
                )
            ),
        ]
    )]
    public function index(): AnonymousResourceCollection
    {
        return BookResource::collection(Book::paginate(2));
    }

    #[OA\Post(
        path: '/books',
        summary: 'Créer un livre',
        description: 'Crée un nouveau livre. Authentification requise.',
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['title', 'author', 'summary', 'isbn'],
                properties: [
                    new OA\Property(property: 'title', type: 'string', minLength: 3, maxLength: 255, example: 'Fondation'),
                    new OA\Property(property: 'author', type: 'string', minLength: 3, maxLength: 100, example: 'Isaac Asimov'),
                    new OA\Property(property: 'summary', type: 'string', minLength: 10, maxLength: 500, example: "Cycle de science-fiction sur la chute et la renaissance d'un empire galactique."),
                    new OA\Property(property: 'isbn', type: 'string', minLength: 13, maxLength: 13, example: '9780553293357'),
                ]
            )
        ),
        tags: ['Books'],
        parameters: [
            new OA\Parameter(ref: '#/components/parameters/AcceptJsonHeader'),
            new OA\Parameter(ref: '#/components/parameters/ContentTypeJsonHeader'),
        ],
        responses: [
            new OA\Response(
                response: 201,
                description: 'Livre créé',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'data', ref: '#/components/schemas/Book'),
                ])
            ),
            new OA\Response(
                response: 401,
                description: 'Non authentifié',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'message', type: 'string', example: 'Unauthenticated.'),
                ])
            ),
            new OA\Response(
                response: 422,
                description: 'Erreur de validation',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'message', type: 'string', example: 'The isbn field must be 13 characters.'),
                    new OA\Property(property: 'errors', type: 'object', example: ['isbn' => ['The isbn field must be 13 characters.']]),
                ])
            ),
        ]
    )]
    public function store(Request $request): BookResource
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'min:3', 'max:255'],
            'author' => ['required', 'string', 'min:3', 'max:100'],
            'summary' => ['required', 'string', 'min:10', 'max:500'],
            'isbn' => ['required', 'string', 'size:13', 'unique:books,isbn'],
        ]);

        $book = Book::create($validated);

        return new BookResource($book);
    }

    #[OA\Get(
        path: '/books/{book}',
        summary: "Détail d'un livre",
        description: "Retourne le détail d'un livre. Réponse mise en cache pendant 60 minutes.",
        tags: ['Books'],
        parameters: [
            new OA\Parameter(ref: '#/components/parameters/AcceptJsonHeader'),
            new OA\Parameter(name: 'book', description: 'ID du livre', in: 'path', required: true, schema: new OA\Schema(type: 'integer', example: 1)),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Détail du livre',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'data', ref: '#/components/schemas/Book'),
                ])
            ),
            new OA\Response(
                response: 404,
                description: 'Livre introuvable',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'message', type: 'string', example: 'No query results for model [App\\Models\\Book] 99.'),
                ])
            ),
        ]
    )]
    public function show(Book $book): BookResource
    {
        $cached = Cache::remember(
            "book.{$book->id}",
            now()->addMinutes(60),
            fn () => $book
        );

        return new BookResource($cached);
    }

    #[OA\Put(
        path: '/books/{book}',
        summary: 'Modifier un livre',
        description: "Met à jour un livre existant. Authentification requise. Tous les champs sont optionnels mais doivent respecter les contraintes s'ils sont fournis.",
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'title', type: 'string', minLength: 3, maxLength: 255, example: '1984 (édition révisée)'),
                    new OA\Property(property: 'author', type: 'string', minLength: 3, maxLength: 100, example: 'George Orwell'),
                    new OA\Property(property: 'summary', type: 'string', minLength: 10, maxLength: 500, example: 'Roman dystopique culte sur le totalitarisme.'),
                    new OA\Property(property: 'isbn', type: 'string', minLength: 13, maxLength: 13, example: '9780451524935'),
                ]
            )
        ),
        tags: ['Books'],
        parameters: [
            new OA\Parameter(ref: '#/components/parameters/AcceptJsonHeader'),
            new OA\Parameter(ref: '#/components/parameters/ContentTypeJsonHeader'),
            new OA\Parameter(name: 'book', description: 'ID du livre', in: 'path', required: true, schema: new OA\Schema(type: 'integer', example: 1)),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Livre mis à jour',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'data', ref: '#/components/schemas/Book'),
                ])
            ),
            new OA\Response(
                response: 401,
                description: 'Non authentifié',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'message', type: 'string', example: 'Unauthenticated.'),
                ])
            ),
            new OA\Response(
                response: 404,
                description: 'Livre introuvable',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'message', type: 'string', example: 'No query results for model [App\\Models\\Book] 99.'),
                ])
            ),
            new OA\Response(
                response: 422,
                description: 'Erreur de validation',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'message', type: 'string', example: 'The title field must be at least 3 characters.'),
                    new OA\Property(property: 'errors', type: 'object', example: ['title' => ['The title field must be at least 3 characters.']]),
                ])
            ),
        ]
    )]
    public function update(Request $request, Book $book): BookResource
    {
        $validated = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'min:3', 'max:255'],
            'author' => ['sometimes', 'required', 'string', 'min:3', 'max:100'],
            'summary' => ['sometimes', 'required', 'string', 'min:10', 'max:500'],
            'isbn' => ['sometimes', 'required', 'string', 'size:13', 'unique:books,isbn,'.$book->id],
        ]);

        $book->update($validated);

        Cache::forget("book.{$book->id}");

        return new BookResource($book);
    }

    #[OA\Delete(
        path: '/books/{book}',
        summary: 'Supprimer un livre',
        description: 'Supprime un livre. Authentification requise.',
        security: [['bearerAuth' => []]],
        tags: ['Books'],
        parameters: [
            new OA\Parameter(ref: '#/components/parameters/AcceptJsonHeader'),
            new OA\Parameter(name: 'book', description: 'ID du livre', in: 'path', required: true, schema: new OA\Schema(type: 'integer', example: 1)),
        ],
        responses: [
            new OA\Response(response: 204, description: 'Livre supprimé (pas de contenu)'),
            new OA\Response(
                response: 401,
                description: 'Non authentifié',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'message', type: 'string', example: 'Unauthenticated.'),
                ])
            ),
            new OA\Response(
                response: 404,
                description: 'Livre introuvable',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'message', type: 'string', example: 'No query results for model [App\\Models\\Book] 99.'),
                ])
            ),
        ]
    )]
    public function destroy(Book $book): JsonResponse
    {
        Cache::forget("book.{$book->id}");

        $book->delete();

        return response()->json(null, 204);
    }
}
