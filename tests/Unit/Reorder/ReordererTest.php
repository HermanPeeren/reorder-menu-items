<?php

declare(strict_types=1);

namespace Yepr\Plugin\Task\ReorderMenuItems\Tests\Unit\Reorder;

use PHPUnit\Framework\TestCase;
use Yepr\Plugin\Task\ReorderMenuItems\Html\ListReorderer;
use Yepr\Plugin\Task\ReorderMenuItems\Menu\MenuItem;
use Yepr\Plugin\Task\ReorderMenuItems\Reorder\Outcome;
use Yepr\Plugin\Task\ReorderMenuItems\Reorder\Reorderer;
use Yepr\Plugin\Task\ReorderMenuItems\Store\Article;
use Yepr\Plugin\Task\ReorderMenuItems\Tests\Support\GuideFixture;
use Yepr\Plugin\Task\ReorderMenuItems\Tests\Support\MemoryMenuStore;

final class ReordererTest extends TestCase
{
    /**
     * @var list<array{0: string, 1: string}>
     */
    private array $log = [];

    private function store(): MemoryMenuStore
    {
        return new MemoryMenuStore(GuideFixture::tree(), GuideFixture::associations(), GuideFixture::articles());
    }

    private function reorder(MemoryMenuStore $store, bool $dryRun, bool $includeSource = true): Outcome
    {
        $reorderer = new Reorderer($store, new ListReorderer(), GuideFixture::rules(), function (string $message, string $priority): void {
            $this->log[] = [$priority, $message];
        });

        return $reorderer->run($dryRun, $includeSource, 42);
    }

    /**
     * @return list<int>
     */
    private static function children(MemoryMenuStore $store, int $parentId): array
    {
        return array_map(static fn (MenuItem $item): int => $item->id, $store->tree()->children($parentId));
    }

    public function testADryRunChangesNothingAndSaysWhatItWouldDo(): void
    {
        $store   = $this->store();
        $outcome = $this->reorder($store, true);

        self::assertSame(0, $store->writes);
        self::assertSame([], $store->saves);
        self::assertSame([10, 30, 140, 50, 40, 130, 110], self::children($store, 1));
        self::assertSame(2, $outcome->menus);
        // English: 10, 14, 30. German: root, 110, 114, 130, 140.
        self::assertSame(8, $outcome->groups);
        self::assertSame(2, $outcome->articles);
        self::assertGreaterThan(0, $outcome->rows);
        self::assertStringContainsString('Dry run', $this->log[\count($this->log) - 1][1]);
        self::assertContains(['info', 'mainmenu-de-de, (top level): Benutzerhandbuch | Adminseiten Referenzen | Mitwirkende'], $this->log);
        self::assertContains(
            ['warning', 'mainmenu-de-de: "Nur Deutsch" (150) is associated with no item of mainmenu; it stays after its siblings that are.'],
            $this->log
        );
    }

    public function testARunPutsEveryMenuInItsOrderAndKeepsTheTreeWhole(): void
    {
        $store = $this->store();
        $this->reorder($store, false);

        $tree = $store->tree();

        self::assertSame(1, $store->writes);
        self::assertSame(0, $tree->inconsistencies());
        // The hidden menu and the English top level keep their places; the German items take theirs.
        self::assertSame([10, 30, 110, 50, 40, 130, 140], self::children($store, 1));
        self::assertSame([111, 113, 112, 114, 123, 122], self::children($store, 110));
        self::assertSame([115, 116, 118, 117, 120, 119, 121], self::children($store, 114));
        self::assertSame([131, 133, 132], self::children($store, 130));
        self::assertSame([141, 142, 150], self::children($store, 140));
        self::assertSame([11, 12, 13, 14, 23, 22], self::children($store, 10));
        self::assertSame([15, 16, 17, 18, 19, 20, 21], self::children($store, 14));
        self::assertSame([41, 42], self::children($store, 40));
        // The root still spans the same numbers: no item lost or added.
        self::assertSame(GuideFixture::tree()->item(1)->rgt, $tree->item(1)->rgt);
    }

    public function testTheFieldListsFollowTheMenuAndAreSavedWithANote(): void
    {
        $store = $this->store();
        $this->reorder($store, false);

        self::assertSame(GuideFixture::fieldList([118, 117, 120, 19], '&amp;lang=de-DE'), $store->articles[GuideFixture::ARTICLE_DE]->introtext);
        self::assertSame(GuideFixture::fieldList([17, 18, 19, 20], ''), $store->articles[GuideFixture::ARTICLE_EN]->introtext);
        self::assertSame([GuideFixture::ARTICLE_EN, GuideFixture::ARTICLE_DE], array_column($store->saves, 'id'));
        self::assertSame(42, $store->saves[0]['user']);
        self::assertSame('Reorder menu items: list in the order of the menu', $store->saves[0]['note']);
    }

    public function testRunningAgainChangesNothing(): void
    {
        $store = $this->store();
        $this->reorder($store, false);

        $this->log = [];
        $outcome   = $this->reorder($store, false);

        self::assertSame(0, $outcome->groups);
        self::assertSame(0, $outcome->rows);
        self::assertSame(0, $outcome->articles);
        self::assertSame(1, $store->writes);
        self::assertCount(2, $store->saves);
    }

    public function testWithoutTheSourceMenuOnlyTheTranslationsChange(): void
    {
        $store   = $this->store();
        $outcome = $this->reorder($store, false, false);

        self::assertSame(1, $outcome->menus);
        self::assertSame([11, 12, 13, 14, 22, 23], self::children($store, 10));
        self::assertSame([111, 113, 112, 114, 123, 122], self::children($store, 110));
        self::assertSame([GuideFixture::ARTICLE_DE], array_column($store->saves, 'id'));
    }

    public function testACheckedOutArticleIsSkippedWithAWarning(): void
    {
        $store   = $this->store();
        $article = $store->articles[GuideFixture::ARTICLE_DE];

        $store->articles[GuideFixture::ARTICLE_DE] = new Article(
            $article->id,
            $article->title,
            $article->language,
            $article->introtext,
            $article->fulltext,
            true
        );

        $this->reorder($store, false);

        self::assertSame([GuideFixture::ARTICLE_EN], array_column($store->saves, 'id'));
        self::assertContains(
            ['warning', 'Article "Felder" (501) is checked out, so it is not changed; check it in and run the task again.'],
            $this->log
        );
        // The menus are reordered all the same.
        self::assertSame([115, 116, 118, 117, 120, 119, 121], self::children($store, 114));
    }

    public function testAMissingArticleIsAWarningNotAFailure(): void
    {
        $store = $this->store();
        unset($store->articles[GuideFixture::ARTICLE_DE]);

        $this->reorder($store, false);

        self::assertContains(['warning', 'mainmenu-de-de: "Felder" opens article 501, which does not exist.'], $this->log);
    }
}
