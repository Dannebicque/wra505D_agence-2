<?php

declare(strict_types=1);

namespace StageBundle\Adapter;

/**
 * Relaie à l'entité adaptée ce que le gabarit de convention lui demande et que l'adaptateur ne
 * redéfinit pas : `proposition.etudiant.nom` lit `Etudiant::getNom()`.
 */
trait ForwardsToEntity
{
    /**
     * Entité adaptée ; null quand le stage n'en a pas, et tout ce qu'on lui demande est alors vide.
     */
    abstract private function forwarded(): ?object;

    /**
     * Une méthode absente lève BadMethodCallException, que Twig rend comme une valeur vide.
     *
     * @param list<mixed> $arguments
     */
    public function __call(string $name, array $arguments): mixed
    {
        $entity = $this->forwarded();
        if ($entity === null) {
            return null;
        }
        if (!method_exists($entity, $name)) {
            throw new \BadMethodCallException(sprintf('%s::%s() n\'existe pas.', $entity::class, $name));
        }
        $method = [$entity, $name];

        return is_callable($method) ? $method(...$arguments) : null;
    }

    public function __get(string $name): mixed
    {
        $getter = $this->getterFor($name);

        return $getter !== null ? $getter() : null;
    }

    public function __isset(string $name): bool
    {
        return $this->getterFor($name) !== null;
    }

    private function getterFor(string $name): ?callable
    {
        $getter = 'get' . ucfirst($name);
        foreach (array_filter([$this, $this->forwarded()], static fn (?object $cible): bool => $cible !== null) as $cible) {
            $callable = [$cible, $getter];
            if (method_exists($cible, $getter) && is_callable($callable)) {
                return $callable;
            }
        }

        return null;
    }
}
