<?php

declare(strict_types=1);

namespace App\ValueObject;

use App\Utils\LooseValue;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * Adresse postale, stockée en JSON dans les entités qui en portent une.
 *
 * Les groupes restent ceux d'origine : le client y a ajouté `etudiant:detail`, or
 * `GET /api/etudiants` est lisible sans connexion, ce qui publierait l'adresse de chaque étudiant.
 */
class Adresse implements \JsonSerializable, \Stringable
{
    private const DEFAULT_COUNTRY = 'France';

    #[Groups(['etudiant:read', 'scolarite:read'])]
    private ?string $adresse;

    #[Groups(['etudiant:read', 'scolarite:read'])]
    private ?string $complement1;

    #[Groups(['etudiant:read', 'scolarite:read'])]
    private ?string $complement2;

    #[Groups(['etudiant:read', 'scolarite:read'])]
    private ?string $ville;

    #[Groups(['etudiant:read', 'scolarite:read'])]
    private ?string $codePostal;

    #[Groups(['etudiant:read', 'scolarite:read'])]
    private string $pays;

    public function __construct(
        ?string $adresse = null,
        ?string $complement1 = null,
        ?string $complement2 = null,
        ?string $ville = null,
        ?string $codePostal = null,
        ?string $pays = self::DEFAULT_COUNTRY,
    ) {
        $this->adresse = self::clean($adresse);
        $this->complement1 = self::clean($complement1);
        $this->complement2 = self::clean($complement2);
        $this->ville = self::clean($ville);
        $this->codePostal = self::clean($codePostal);
        $this->pays = self::clean($pays) ?? self::DEFAULT_COUNTRY;
    }

    public function getAdresse(): ?string
    {
        return $this->adresse;
    }

    public function setAdresse(?string $adresse): self
    {
        $this->adresse = self::clean($adresse);

        return $this;
    }

    public function getComplement1(): ?string
    {
        return $this->complement1;
    }

    public function setComplement1(?string $complement1): self
    {
        $this->complement1 = self::clean($complement1);

        return $this;
    }

    public function getComplement2(): ?string
    {
        return $this->complement2;
    }

    public function setComplement2(?string $complement2): self
    {
        $this->complement2 = self::clean($complement2);

        return $this;
    }

    public function getVille(): ?string
    {
        return $this->ville;
    }

    public function setVille(?string $ville): self
    {
        $this->ville = self::clean($ville);

        return $this;
    }

    public function getCodePostal(): ?string
    {
        return $this->codePostal;
    }

    public function setCodePostal(?string $codePostal): self
    {
        $this->codePostal = self::clean($codePostal);

        return $this;
    }

    public function getPays(): string
    {
        return $this->pays;
    }

    public function setPays(?string $pays): self
    {
        $this->pays = self::clean($pays) ?? self::DEFAULT_COUNTRY;

        return $this;
    }

    public function isEmpty(): bool
    {
        return $this->adresse === null
            && $this->complement1 === null
            && $this->complement2 === null
            && $this->ville === null
            && $this->codePostal === null;
    }

    /**
     * Adresse sur une ligne ; le pays n'est cité que hors de France.
     */
    public function getInlineAdresse(): string
    {
        $pays = mb_strtolower($this->pays) !== mb_strtolower(self::DEFAULT_COUNTRY) ? $this->pays : null;

        return implode(', ', $this->lines($pays));
    }

    public function getFullAdresse(string $separator = "\n"): string
    {
        return implode($separator, $this->lines($this->pays));
    }

    public function __toString(): string
    {
        return $this->getInlineAdresse();
    }

    public function equals(?self $other): bool
    {
        return $other !== null && $this->toArray() === $other->toArray();
    }

    /** @return array{adresse: string, complement1: string, complement2: string, ville: string, codePostal: string, pays: string} */
    public function toArray(): array
    {
        return [
            'adresse' => $this->adresse ?? '',
            'complement1' => $this->complement1 ?? '',
            'complement2' => $this->complement2 ?? '',
            'ville' => $this->ville ?? '',
            'codePostal' => $this->codePostal ?? '',
            'pays' => $this->pays,
        ];
    }

    /** @return array{adresse: string, complement1: string, complement2: string, ville: string, codePostal: string, pays: string} */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * Accepte aussi les clés de l'intranet V3 et de l'import Apogée (`LIB_AD1`, `cp`, `commune`…).
     * Rend null pour une adresse vide, plutôt qu'un objet sans contenu.
     *
     * @param array<array-key, mixed>|null $data
     */
    public static function fromArray(?array $data): ?self
    {
        if ($data === null || $data === []) {
            return null;
        }

        $adresse = new self(
            self::first($data, ['adresse', 'adresse1', 'rue', 'LIB_AD1']),
            self::first($data, ['complement1', 'adresse2', 'complement', 'LIB_AD2']),
            self::first($data, ['complement2', 'adresse3', 'LIB_AD3']),
            self::first($data, ['ville', 'commune']),
            self::first($data, ['codePostal', 'code_postal', 'codepostal', 'cp']),
            self::first($data, ['pays']),
        );

        return $adresse->isEmpty() ? null : $adresse;
    }

    /**
     * @param array<array-key, mixed> $data
     * @param list<string>            $keys
     */
    private static function first(array $data, array $keys): ?string
    {
        foreach ($keys as $key) {
            if (isset($data[$key])) {
                return LooseValue::nullableString($data[$key]);
            }
        }

        return null;
    }

    private static function clean(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $value = trim($value);

        return $value === '' ? null : $value;
    }

    /** @return list<string> */
    private function lines(?string $pays): array
    {
        $ville = trim(($this->codePostal ?? '') . ' ' . ($this->ville ?? ''));

        return array_values(array_filter(
            [$this->adresse, $this->complement1, $this->complement2, $ville, $pays],
            static fn (?string $line): bool => $line !== null && $line !== '',
        ));
    }
}
