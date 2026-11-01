<?php

declare(strict_types=1);

namespace Yepr\Plugin\Task\ReorderMenuItems\Tests\Unit\Order;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Yepr\Plugin\Task\ReorderMenuItems\Order\TitleCollator;

final class TitleCollatorTest extends TestCase
{
    /**
     * @param list<string> $titles
     *
     * @return list<string>
     */
    private static function sorted(TitleCollator $collator, array $titles): array
    {
        usort($titles, $collator->compare(...));

        return $titles;
    }

    /**
     * @return iterable<string, array{0: bool}>
     */
    public static function withAndWithoutIntl(): iterable
    {
        yield 'intl' => [true];
        yield 'fallback' => [false];
    }

    #[DataProvider('withAndWithoutIntl')]
    public function testCaseDoesNotCount(bool $intl): void
    {
        self::assertSame(
            ['Scheduled Tasks', 'Schema.org', 'SEO', 'Smart Search'],
            self::sorted(new TitleCollator('en-GB', $intl), ['SEO', 'Smart Search', 'Scheduled Tasks', 'Schema.org'])
        );
    }

    #[DataProvider('withAndWithoutIntl')]
    public function testAccentsSortWithTheirLetter(bool $intl): void
    {
        self::assertSame(['Abmelden', 'Änderungen', 'Banner'], self::sorted(new TitleCollator('de-DE', $intl), ['Banner', 'Änderungen', 'Abmelden']));
        self::assertSame(
            ['Éditeurs', 'Extensions', 'Gestion'],
            self::sorted(new TitleCollator('fr-FR', $intl), ['Gestion', 'Extensions', 'Éditeurs'])
        );
    }

    #[DataProvider('withAndWithoutIntl')]
    public function testCyrillicIsInAlphabeticalOrder(bool $intl): void
    {
        self::assertSame(['Баннеры', 'Меню', 'Статьи'], self::sorted(new TitleCollator('ru-RU', $intl), ['Статьи', 'Баннеры', 'Меню']));
    }

    #[DataProvider('withAndWithoutIntl')]
    public function testQuotationMarksDoNotCount(bool $intl): void
    {
        self::assertSame(
            ['Campo de calendario', "Campo 'Editor'", 'Campo entero', "Campo 'Lista'"],
            self::sorted(new TitleCollator('es-ES', $intl), ["Campo 'Lista'", 'Campo entero', "Campo 'Editor'", 'Campo de calendario'])
        );
        self::assertSame(
            ['Поле «Дочерняя форма»', 'Поле и группы полей', 'Поле «Календарь»'],
            self::sorted(new TitleCollator('ru-RU', $intl), ['Поле и группы полей', 'Поле «Календарь»', 'Поле «Дочерняя форма»'])
        );
    }

    #[DataProvider('withAndWithoutIntl')]
    public function testSpacesCount(bool $intl): void
    {
        self::assertSame(
            ['List Field', 'List of Images Field', 'Text Field', 'Textarea Field'],
            self::sorted(new TitleCollator('en-GB', $intl), ['Textarea Field', 'List of Images Field', 'Text Field', 'List Field'])
        );
    }

    public function testPersianLettersAreInPersianOrder(): void
    {
        if (!class_exists(\Collator::class)) {
            self::markTestSkipped('Needs the intl extension.');
        }

        // پ comes after ب and before ت in Persian; by code point it would come last.
        self::assertSame(['بنر', 'پلاگین', 'تگ'], self::sorted(new TitleCollator('fa-IR'), ['تگ', 'پلاگین', 'بنر']));
    }

    public function testNumbersAreComparedAsNumbers(): void
    {
        if (!class_exists(\Collator::class)) {
            self::markTestSkipped('Needs the intl extension.');
        }

        self::assertSame(['Joomla 4', 'Joomla 5', 'Joomla 10'], self::sorted(new TitleCollator('en-GB'), ['Joomla 10', 'Joomla 4', 'Joomla 5']));
    }

    #[DataProvider('withAndWithoutIntl')]
    public function testTitlesThatAreEqualForAReaderStillHaveAFixedOrder(bool $intl): void
    {
        $collator = new TitleCollator('en-GB', $intl);

        self::assertSame(-$collator->compare('Seo', 'SEO'), $collator->compare('SEO', 'Seo'));
        self::assertNotSame(0, $collator->compare('SEO', 'Seo'));
        self::assertSame(0, $collator->compare('SEO', 'SEO'));
    }
}
