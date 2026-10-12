<?php

declare(strict_types=1);

namespace App\Migration\IntranetV3\Enrolment;

use App\Entity\Structure\StructurePn;
use App\Entity\Structure\StructureSemestre;

/**
 * Semestre proposé pour la suite du parcours, à partir du texte libre de la V3 (« BUT 2 », « S3 »).
 *
 * Règle d'E19 portée au modèle du client : le texte désigne une année (libellé court ou long) ou un
 * semestre du PN de l'étudiant ; une année vaut son premier semestre. Une seule correspondance est
 * retenue, sinon rien : on ne choisit pas au hasard entre deux années de même libellé. Du client :
 * casse et espaces ignorés, et les marques « ? », « E.C. », « EC » ne proposent rien.
 */
final class ProposedSemesterResolver
{
    private const NO_PROPOSITION = ['?', 'E.C.', 'EC'];

    public static function resolve(string $proposition, StructurePn $pn): ?StructureSemestre
    {
        $wanted = self::normalize($proposition);
        if ($wanted === '' || in_array($wanted, self::NO_PROPOSITION, true)) {
            return null;
        }

        $matches = [];
        foreach ($pn->getAnnees() as $year) {
            $semesters = $year->getSemestres()->toArray();
            usort($semesters, static fn (StructureSemestre $a, StructureSemestre $b): int => ($a->getOrdreLmd() ?? 0) <=> ($b->getOrdreLmd() ?? 0));

            if (in_array($wanted, [self::normalize($year->getLibelle()), self::normalize($year->getLibelleLong())], true) && $semesters !== []) {
                $matches[spl_object_id($semesters[0])] = $semesters[0];
            }
            foreach ($semesters as $semester) {
                if ($wanted === self::normalize($semester->getLibelle())) {
                    $matches[spl_object_id($semester)] = $semester;
                }
            }
        }

        return count($matches) === 1 ? reset($matches) : null;
    }

    private static function normalize(?string $label): string
    {
        return mb_strtoupper(trim($label ?? ''));
    }
}
