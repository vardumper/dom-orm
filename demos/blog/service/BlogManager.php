<?php

declare(strict_types=1);

use DOM\ORM\Repository\EntityRepository;
use DOM\ORM\Traits\EntityManagerTrait;

final class BlogManager
{
    use EntityManagerTrait;

    public function createArticle(string $title, string $body, string $author, string $subline = '', ?Image $image = null): string
    {
        $article = new Article($title, $body, $author, $subline, $image !== null ? [$image] : []);
        $this->persist($article);

        return $article->getId();
    }

    public function addComment(string $articleId, string $author, string $body): string
    {
        $repository = new EntityRepository(Article::class);
        $article = $repository->find($articleId);
        if (!$article instanceof Article) {
            throw new \RuntimeException('Article not found: ' . $articleId);
        }

        $comment = new Comment($author, $body);
        $article->addComment($comment);
        $this->persist($article);

        return $comment->getId();
    }

    public function findAllArticles(): array
    {
        $collection = (new EntityRepository(Article::class))->findAll();
        if ($collection === null) {
            return [];
        }

        // Newest first. The repository returns items in document (insertion)
        // order, so sort by createdAt descending. usort() is stable on PHP 8+,
        // so articles sharing a timestamp keep their original relative order.
        $articles = array_values($collection->toArray());
        usort($articles, static function (Article $a, Article $b): int {
            return $b->getCreatedAt() <=> $a->getCreatedAt();
        });

        return $articles;
    }

    public function findArticle(string $id): ?Article
    {
        $article = (new EntityRepository(Article::class))->find($id);

        return $article instanceof Article ? $article : null;
    }

    public function removeArticle(string $id): void
    {
        (new EntityRepository(Article::class))->remove($id);
    }

    public function removeComment(string $articleId, string $commentId): void
    {
        $repository = new EntityRepository(Article::class);
        $article = $repository->find($articleId);
        if (!$article instanceof Article) {
            throw new \RuntimeException('Article not found: ' . $articleId);
        }

        $filtered = array_values(array_filter(
            $article->getComments(),
            static fn (Comment $c): bool => $c->getId() !== $commentId,
        ));

        // Re-create the article with filtered comments.
        $updated = new Article(
            $article->getTitle(),
            $article->getBody(),
            $article->getAuthor(),
            $article->getSubline(),
            $article->getImages(),
            $filtered,
            $article->getId(),
            $article->getCreatedAt(),
        );
        $this->persist($updated);
    }
}
