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
        $this->assertSame('en', i18n_detect('de-DE,en;q=0.5'));
        $this->assertSame('en', i18n_detect(''));
    }

    public function testPolishTranslationIsComplete(): void
    {
        $translations = require dirname(__DIR__).'/lang/pl.php';
        $missing = array_diff(i18n_collect_strings(dirname(__DIR__)), array_keys($translations));
        $this->assertSame([], array_values($missing), 'Missing Polish translations (php bin/i18n-strings.php pl)');
    }

    public function testPlaceholdersMatch(): void
    {
        $translations = require dirname(__DIR__).'/lang/pl.php';
        foreach ($translations as $source => $translated) {
            preg_match_all('/%[sd]/', $source, $a);
            preg_match_all('/%[sd]/', $translated, $b);
            $this->assertSame($a[0], $b[0], 'Placeholders differ for: '.$source);
        }
    }
}
