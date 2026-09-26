<?php

namespace App\Enum;

enum TypePublicEnum: string
{
    case TYPE_PUBLIC_ETUDIANT = 'etudiant';
    case TYPE_PUBLIC_PERSONNEL = 'personnel';


    /** @return array<string, self> */
    public static function getChoices(): array
    {
        return [
            'choice.' . self::TYPE_PUBLIC_ETUDIANT->value => self::TYPE_PUBLIC_ETUDIANT,
            'choice.' . self::TYPE_PUBLIC_PERSONNEL->value => self::TYPE_PUBLIC_PERSONNEL,
        ];
    }

    /** @return list<self> */
    public static function getTypes(): array
    {
        return [
            self::TYPE_PUBLIC_ETUDIANT,
            self::TYPE_PUBLIC_PERSONNEL,
        ];
    }
}
