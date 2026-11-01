<?php

declare(strict_types=1);

namespace Yepr\Plugin\Task\ReorderMenuItems\Tests\Unit\Order;

use PHPUnit\Framework\TestCase;
use Yepr\Plugin\Task\ReorderMenuItems\Order\AlphabeticalRule;
use Yepr\Plugin\Task\ReorderMenuItems\Order\MenuOrderPlanner;
use Yepr\Plugin\Task\ReorderMenuItems\Order\MenuPlan;
use Yepr\Plugin\Task\ReorderMenuItems\Order\TitleCollator;
use Yepr\Plugin\Task\ReorderMenuItems\Tests\Support\GuideFixture;
use Yepr\Plugin\Task\ReorderMenuItems\Tests\Support\TreeBuilder;

final class MenuOrderPlannerTest extends TestCase
{
    /**
     * @param list<AlphabeticalRule>|null $rules
     */
    private function plan(string $menutype, string $language, ?array $rules = null): MenuPlan
    {
        $sourceOf = [];

        foreach (GuideFixture::associations() as [$source, $translation]) {
            $sourceOf[$translation] = $source;
        }

        $planner = new MenuOrderPlanner(GuideFixture::tree(), 'mainmenu', $sourceOf, $rules ?? GuideFixture::rules());

        return $planner->plan($menutype, new TitleCollator($language));
    }

    public function testTheTopLevelOfATranslationFollowsTheSourceAndLeavesOtherMenusAlone(): void
    {
        // Only the German items are in the plan; the root's other children keep their places.
        self::assertSame([110, 130, 140], $this->plan('mainmenu-de-de', 'de-DE')->orders[1]);
    }

    public function testChildrenWithoutARuleFollowTheSourceNotTheAlphabet(): void
    {
        // Child B before Child A, as in English; the item without a source comes last.
        self::assertSame([141, 142, 150], $this->plan('mainmenu-de-de', 'de-DE')->orders[140]);
    }

    public function testAnItemWithoutASourceIsReported(): void
    {
        self::assertSame([150], $this->plan('mainmenu-de-de', 'de-DE')->unmatched);
    }

    public function testExplanationsAreAlphabeticalInTheirOwnLanguageWithGettingStartedOnTop(): void
    {
        // Erste Schritte, then Banner, Beiträge, Felder, Geplante Aufgaben, SEO: not the
        // English order (Articles, Banners, ...), and SEO after Geplante Aufgaben.
        self::assertSame([111, 113, 112, 114, 123, 122], $this->plan('mainmenu-de-de', 'de-DE')->orders[110]);
    }

    public function testAdminPagesAreAlphabeticalWithHelpScreensOnTop(): void
    {
        self::assertSame([131, 133, 132], $this->plan('mainmenu-de-de', 'de-DE')->orders[130]);
    }

    public function testOnlyTheFieldTypesAreSortedAndThePagesAroundThemStay(): void
    {
        // Felder und Feldgruppen, Feldparameter | Editor, Kalender, Text, Unterformular | Template-Overrides.
        $plan = $this->plan('mainmenu-de-de', 'de-DE');

        self::assertSame([115, 116, 118, 117, 120, 119, 121], $plan->orders[114]);
        self::assertSame([114 => [115, 116, 118, 117, 120, 119, 121]], $plan->listOrders);
    }

    public function testTheSourceMenuKeepsItsOrderOutsideTheRules(): void
    {
        $plan = $this->plan('mainmenu', 'en-GB');

        self::assertArrayNotHasKey(1, $plan->orders);
        self::assertArrayNotHasKey(40, $plan->orders);
        self::assertSame([], $plan->unmatched);
    }

    public function testTheSourceMenuIsSortedByTheRulesToo(): void
    {
        $plan = $this->plan('mainmenu', 'en-GB');

        // Scheduled Tasks before SEO, ignoring case; Admin Modules before Articles.
        self::assertSame([11, 12, 13, 14, 23, 22], $plan->orders[10]);
        self::assertSame([31, 33, 32], $plan->orders[30]);
        self::assertSame([15, 16, 17, 18, 19, 20, 21], $plan->orders[14]);
    }

    public function testWithoutRulesATranslationFollowsTheSourceEverywhere(): void
    {
        $plan = $this->plan('mainmenu-de-de', 'de-DE', []);

        self::assertSame([111, 112, 113, 114, 122, 123], $plan->orders[110]);
        self::assertSame([115, 116, 117, 118, 120, 119, 121], $plan->orders[114]);
        self::assertSame([], $plan->listOrders);
    }

    public function testAMenuInTheRightOrderHasNothingToChange(): void
    {
        $tree = TreeBuilder::fromOutline([
            [10, 'A', 'mainmenu', [[11, 'A1', 'mainmenu', []], [12, 'A2', 'mainmenu', []]]],
            [20, 'B', 'mainmenu-nl-nl', [[21, 'B1', 'mainmenu-nl-nl', []], [22, 'B2', 'mainmenu-nl-nl', []]]],
        ]);

        $plan = (new MenuOrderPlanner($tree, 'mainmenu', [20 => 10, 21 => 11, 22 => 12], []))->plan('mainmenu-nl-nl', new TitleCollator('nl-NL'));

        self::assertSame([], $plan->orders);
    }

    public function testTheRulesOfTheUserGuideNameItsPaths(): void
    {
        $rules = AlphabeticalRule::userGuide();

        self::assertSame(
            ['user-guide', 'help-pages-references', 'user-guide/fields'],
            array_map(static fn (AlphabeticalRule $r): string => $r->parentPath, $rules)
        );
        self::assertSame('user-guide/getting-started', $rules[0]->stickyPath);
        self::assertSame('help-pages-references/help-screens', $rules[1]->stickyPath);
        self::assertTrue($rules[2]->sortArticleList);
        self::assertNotNull($rules[2]->onlyMatching);
        self::assertSame(1, preg_match($rules[2]->onlyMatching, 'List of Images Field'));
        self::assertSame(0, preg_match($rules[2]->onlyMatching, 'Fields and Field Groups'));
        self::assertSame(0, preg_match($rules[2]->onlyMatching, 'Template Overrides'));
    }
}
