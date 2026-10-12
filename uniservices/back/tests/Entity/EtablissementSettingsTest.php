<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\Etablissement;
use PHPUnit\Framework\TestCase;

/**
 * Les clés d'API des intégrations s'écrivent, ne se relisent jamais, et ne s'effacent pas quand un
 * formulaire renvoie les réglages sans elles.
 */
final class EtablissementSettingsTest extends TestCase
{
    public function testNeverExposesAnApiKeyButSaysItExists(): void
    {
        $institution = (new Etablissement())->setSettings(['integrations' => [
            'edusign' => ['enabled' => true, 'scope' => ['FI'], 'apiKey' => 'secret-edusign', 'apiUrl' => 'https://api.edusign.fr'],
        ]]);

        $masked = $institution->getMaskedSettings()['integrations'];

        self::assertNull($masked['edusign']['apiKey']);
        self::assertTrue($masked['edusign']['apiKeySet']);
        self::assertFalse($masked['orebut']['apiKeySet']);
        self::assertSame('https://api.edusign.fr', $masked['edusign']['apiUrl']);
        self::assertStringNotContainsString('secret', (string) json_encode($institution->getMaskedSettings()));
    }

    public function testKeepsTheStoredKeyWhenTheFormSendsNone(): void
    {
        $institution = (new Etablissement())->setSettings(['integrations' => ['edusign' => ['apiKey' => 'secret-edusign']]]);

        $institution->setSettings(['integrations' => ['edusign' => ['enabled' => true, 'apiKey' => '  ']]]);

        self::assertSame('secret-edusign', $institution->getSettings()['integrations']['edusign']['apiKey']);
        self::assertTrue($institution->getSettings()['integrations']['edusign']['enabled']);
    }

    public function testReplacesTheKeyWhenANewOneIsGiven(): void
    {
        $institution = (new Etablissement())->setSettings(['integrations' => ['orebut' => ['apiKey' => 'ancienne']]]);

        $institution->setSettings(['integrations' => ['orebut' => ['apiKey' => 'nouvelle']]]);

        self::assertSame('nouvelle', $institution->getSettings()['integrations']['orebut']['apiKey']);
    }

    public function testKeepsOnlyKnownEdusignScopesAndIgnoresUnknownKeys(): void
    {
        $institution = (new Etablissement())->setSettings(['integrations' => [
            'edusign' => ['scope' => ['FC', 'XX', 'FI'], 'enabled' => 'yes'],
            'inconnue' => ['apiKey' => 'x'],
        ]]);

        $settings = $institution->getSettings()['integrations'];

        self::assertSame(['FI', 'FC'], $settings['edusign']['scope']);
        self::assertFalse($settings['edusign']['enabled']);
        self::assertSame(['edusign', 'orebut'], array_keys($settings));
    }
}
