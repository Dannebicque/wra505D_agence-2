<?php

namespace App\Security;

class PermissionDefinition
{
    public function __construct(
        private readonly string $code,
        private readonly string $role,
        private readonly string $label,
        private readonly string $package,
        /** @var list<string> $inheritedRoles */
        private readonly array $inheritedRoles = [],
        private readonly bool $defaultAccess = false
    ) {
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function getRole(): string
    {
        return $this->role;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function getPackage(): string
    {
        return $this->package;
    }

    /** @return list<string> */
    public function getInheritedRoles(): array
    {
        return $this->inheritedRoles;
    }

    public function isDefaultAccess(): bool
    {
        return $this->defaultAccess;
    }

    /** @return array{code: string, role: string, label: string, package: string, inheritedRoles: list<string>, defaultAccess: bool} */
    public function toArray(): array
    {
        return [
            'code' => $this->code,
            'role' => $this->role,
            'label' => $this->label,
            'package' => $this->package,
            'inheritedRoles' => $this->inheritedRoles,
            'defaultAccess' => $this->defaultAccess,
        ];
    }
}
