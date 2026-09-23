<?php

use PHPUnit\Framework\TestCase;

final class I18nTest extends TestCase
{
    protected function tearDown(): void
    {
        i18n_set_language('en');
    }

    public function testEnglishReturnsSourceText(): void
    {
        i18n_set_language('en');
        $this->assertSame('Shut down', t('Shut down'));
        $this->assertSame('Unknown machine: vm1', t('Unknown machine: %s', 'vm1'));
    }

    public function testPolishTranslationWithArguments(): void
    {
        i18n_set_language('pl');
        $this->assertSame('Wyłącz', t('Shut down'));
        $this->assertSame('Nieznana maszyna: vm1', t('Unknown machine: %s', 'vm1'));
    }

    public function testMissingTranslationFallsBackToEnglish(): void
    {
        i18n_set_language('pl');
        $this->assertSame('Not translated', t('Not translated'));
    }

    public function testUnknownLanguageFallsBackToEnglish(): void
    {
        i18n_set_language('xx');
        $this->assertSame('en', i18n_language());
    }

    public function testDetectFromAcceptLanguage(): void
    {
        $this->assertSame('pl', i18n_detect('pl-PL,pl;q=0.9,en;q=0.8'));
        $this->assertSame('en', i18n_detect('fr-FR,it;q=0.5'));
        $this->assertSame('en', i18n_detect(''));
        $this->assertSame('nb', i18n_detect('no-NO,no;q=0.9'));
        $this->assertSame('nb', i18n_detect('nn'));
        $this->assertSame('uk', i18n_detect('uk-UA,uk;q=0.9,ru;q=0.8'));
        $this->assertSame('de', i18n_detect('de-AT'));
        $this->assertSame('es', i18n_detect('es-MX,es;q=0.9'));
    }

    public static function languages(): array
    {
        return array_map(fn($code) => [$code], array_values(array_diff(array_keys(LANGUAGES), ['en'])));
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('languages')]
    public function testTranslationIsComplete(string $lang): void
    {
        $translations = require dirname(__DIR__).'/lang/'.$lang.'.php';
        $missing = array_diff(i18n_collect_strings(dirname(__DIR__)), array_keys($translations));
        $this->assertSame([], array_values($missing), "Missing translations (php bin/i18n-strings.php $lang)");
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('languages')]
    public function testPlaceholdersMatch(string $lang): void
    {
        $translations = require dirname(__DIR__).'/lang/'.$lang.'.php';
        foreach ($translations as $source => $translated) {
            preg_match_all('/%[0-9]*[sd]/', $source, $a);
            preg_match_all('/%[0-9]*[sd]/', $translated, $b);
            $this->assertSame($a[0], $b[0], "[$lang] placeholders differ for: $source");
            $this->assertNotSame('', trim($translated), "[$lang] empty translation for: $source");
        }
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('languages')]
    public function testEveryLanguageFileExists(string $lang): void
    {
        $this->assertFileExists(dirname(__DIR__).'/lang/'.$lang.'.php');
    }
}
