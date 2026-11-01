<?php

declare(strict_types=1);

namespace Yepr\Plugin\Task\ReorderMenuItems\Tests\Support;

use Yepr\Plugin\Task\ReorderMenuItems\Menu\MenuTree;
use Yepr\Plugin\Task\ReorderMenuItems\Order\AlphabeticalRule;
use Yepr\Plugin\Task\ReorderMenuItems\Store\Article;

/**
 * A small User Guide: the English main menu, a German translation of it in the order
 * Translator Feedback leaves it (every item appended as it was translated), and another
 * menu whose top level sits between theirs.
 *
 * Source item N has German translation 100 + N.
 */
final class GuideFixture
{
    public const ARTICLE_EN = 500;
    public const ARTICLE_DE = 501;

    public static function tree(): MenuTree
    {
        /** @param array<mixed> $children */
        $en = static fn (int $id, string $title, array $children = [], string $link = ''): array
            => [$id, $title, 'mainmenu', $children, 'en-GB', $link];
        /** @param array<mixed> $children */
        $de = static fn (int $id, string $title, array $children = [], string $link = ''): array
            => [$id, $title, 'mainmenu-de-de', $children, 'de-DE', $link];

        return TreeBuilder::fromOutline([
            $en(10, 'User Guide', [
                $en(11, 'Getting Started'),
                $en(12, 'Articles'),
                $en(13, 'Banners'),
                $en(14, 'Fields', [
                    $en(15, 'Fields and Field Groups'),
                    $en(16, 'Field Parameters'),
                    $en(17, 'Calendar Field'),
                    $en(18, 'Editor Field'),
                    $en(20, 'Text Field'),
                    $en(19, 'Subform Field'),
                    $en(21, 'Template Overrides'),
                ], 'index.php?option=com_content&view=article&id=' . self::ARTICLE_EN),
                $en(22, 'SEO'),
                $en(23, 'Scheduled Tasks'),
            ]),
            $en(30, 'Admin Pages References', [
                $en(31, 'Help Screens'),
                $en(32, 'Articles'),
                $en(33, 'Admin Modules'),
            ]),
            $de(140, 'Mitwirkende', [
                [150, 'Nur Deutsch', 'mainmenu-de-de', [], 'de-DE'],
                $de(142, 'Kind A'),
                $de(141, 'Kind B'),
            ]),
            [50, 'Hidden', 'hiddenmenu', []],
            $en(40, 'Contributors', [
                $en(41, 'Child B'),
                $en(42, 'Child A'),
            ]),
            $de(130, 'Adminseiten Referenzen', [
                $de(132, 'Beiträge'),
                $de(133, 'Admin-Module'),
                $de(131, 'Hilfe-Bildschirme'),
            ]),
            $de(110, 'Benutzerhandbuch', [
                $de(123, 'Geplante Aufgaben'),
                $de(114, 'Felder', [
                    $de(121, 'Template-Overrides'),
                    $de(120, 'Text-Feld'),
                    $de(119, 'Unterformular-Feld'),
                    $de(118, 'Editor-Feld'),
                    $de(117, 'Kalender-Feld'),
                    $de(116, 'Feldparameter'),
                    $de(115, 'Felder und Feldgruppen'),
                ], 'index.php?option=com_content&view=article&id=' . self::ARTICLE_DE),
                $de(113, 'Banner'),
                $de(112, 'Beiträge'),
                $de(111, 'Erste Schritte'),
                $de(122, 'SEO'),
            ]),
        ]);
    }

    /**
     * @return list<list<int>>
     */
    public static function associations(): array
    {
        $groups = [];

        foreach ([10, 11, 12, 13, 14, 15, 16, 17, 18, 19, 20, 21, 22, 23, 30, 31, 32, 33, 40, 41, 42] as $id) {
            $groups[] = [$id, 100 + $id];
        }

        return $groups;
    }

    /**
     * The rules of the User Guide, for this menu's paths.
     *
     * @return list<AlphabeticalRule>
     */
    public static function rules(): array
    {
        return [
            new AlphabeticalRule('user-guide', 'user-guide/getting-started'),
            new AlphabeticalRule('admin-pages-references', 'admin-pages-references/help-screens'),
            new AlphabeticalRule('user-guide/fields', null, '/\sField$/', true),
        ];
    }

    /**
     * @return list<Article>
     */
    public static function articles(): array
    {
        return [
            new Article(self::ARTICLE_EN, 'Fields', 'en-GB', self::fieldList([17, 18, 20, 19], ''), ''),
            // One entry still links to the English menu item (19).
            new Article(self::ARTICLE_DE, 'Felder', 'de-DE', self::fieldList([117, 118, 19, 120], '&amp;lang=de-DE'), ''),
        ];
    }

    /**
     * An overview as in the User Guide: an introduction with a list, the field types
     * (one of them wrapped in a div), and related topics.
     *
     * @param list<int> $itemids
     */
    public static function fieldList(array $itemids, string $lang): string
    {
        $entries = [];

        foreach ($itemids as $itemid) {
            $link      = '<a title="T' . $itemid . '" href="index.php?Itemid=' . $itemid . $lang . '">Type ' . $itemid . '</a> - text';
            $entries[] = $itemid === 19 || $itemid === 119 ? "<li>\n<div>" . $link . "</div>\n</li>" : '<li>' . $link . '</li>';
        }

        return "<p>Intro</p>\n<ul>\n<li><strong>Content</strong>: Articles.</li>\n<li><strong>Users</strong>.</li>\n</ul>\n"
            . "<h2>Field Types</h2>\n<ul>\n" . implode("\n", $entries) . "\n</ul>\n"
            . "<h2>Related Topics</h2>\n<ul>\n<li><a href=\"index.php?Itemid=21\">Template Overrides</a></li>\n</ul>";
    }
}
