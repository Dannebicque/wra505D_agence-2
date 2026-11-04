<?php

namespace IntranetBundle\Service\Enseignement;

use App\Entity\Users\Personnel;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException};

/**
 * Contexte commun aux Providers et au Processor ; aucune confiance dans l'identité envoyée par le client.
 *
 * @phpstan-import-type Contexte from AvancementEnseignementService
 */
final class AvancementEnseignementContexte
{
    public function __construct(
        private readonly Security $security,
        private readonly RequestStack $requestStack,
        private readonly AvancementEnseignementService $service,
    ) {
    }

    public function enseignant(): Personnel
    {
        $user = $this->security->getUser();
        if (!$user instanceof Personnel) {
            throw new AccessDeniedHttpException('Compte enseignant requis.');
        }
        return $user;
    }

    /**
     * @phpstan-return Contexte
     */
    public function charger(): array
    {
        return $this->service->contexte($this->enseignant(), $this->entier('enseignement', true), $this->entier('anneeUniversitaire'));
    }

    /**
     * @return ($obligatoire is true ? int : int|null)
     */
    public function entier(string $nom, bool $obligatoire = false): ?int
    {
        $request = $this->requestStack->getCurrentRequest();
        if (null === $request || !$request->query->has($nom)) {
            if ($obligatoire) {
                throw new BadRequestHttpException('Paramètre obligatoire : '.$nom);
            }
            return null;
        }
        $value = filter_var($request->query->get($nom), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($value === false) {
            throw new BadRequestHttpException('Paramètre invalide : '.$nom);
        }
        return $value;
    }
}
