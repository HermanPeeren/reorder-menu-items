<?php

declare(strict_types=1);

namespace Yepr\Plugin\Task\ReorderMenuItems\Tests\Support;

use Yepr\Plugin\Task\ReorderMenuItems\Menu\MenuTree;
use Yepr\Plugin\Task\ReorderMenuItems\Store\Article;
use Yepr\Plugin\Task\ReorderMenuItems\Store\MenuStore;

/**
 * The site in memory: writes change the tree it hands out next.
 */
final class MemoryMenuStore implements MenuStore
{
    /**
     * @var array<int, Article>
     */
    public array $articles = [];

    /**
     * @var list<array{id: int, note: string, user: int}>
     */
    public array $saves = [];

    public int $writes = 0;

    /**
     * @param list<list<int>> $associations
     * @param list<Article>   $articles
     */
    public function __construct(private MenuTree $tree, private readonly array $associations, array $articles = [])
    {
        foreach ($articles as $article) {
            $this->articles[$article->id] = $article;
        }
    }

    public function tree(): MenuTree
    {
        return $this->tree;
    }

    public function menuAssociations(): array
    {
        return $this->associations;
    }

    public function writeNumbers(array $numbers): void
    {
        $this->writes++;
        $this->tree = TreeBuilder::apply($this->tree, $numbers);
    }

    public function article(int $id): ?Article
    {
        return $this->articles[$id] ?? null;
    }

    public function saveArticle(Article $article, string $introtext, string $fulltext, int $userId, string $note): void
    {
        $this->articles[$article->id] = new Article($article->id, $article->title, $article->language, $introtext, $fulltext, $article->checkedOut);
        $this->saves[]                = ['id' => $article->id, 'note' => $note, 'user' => $userId];
    }
}
