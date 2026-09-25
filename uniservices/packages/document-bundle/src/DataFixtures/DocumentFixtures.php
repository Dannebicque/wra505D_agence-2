<?php

namespace DocumentBundle\DataFixtures;

use App\Entity\Scolarite\ScolEnseignement;
use DocumentBundle\Entity\Document;
use DocumentBundle\Entity\DocumentCategory;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\DataFixtures\OrderedFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class DocumentFixtures extends Fixture implements OrderedFixtureInterface, FixtureGroupInterface
{
    public static function getGroups(): array
    {
        return ['document', 'default'];
    }

    public function getOrder(): int
    {
        return 20;
    }

    public function load(ObjectManager $manager): void
    {
        $categoriesData = [
            [
                'libelle' => 'Ressources Humaines',
                'icon' => 'pi pi-users',
                'color' => 'bg-blue-500',
                'packageKey' => 'intranet',
                'isSystem' => true,
                'children' => [
                    ['libelle' => 'Contrats', 'icon' => 'pi pi-id-card', 'color' => 'bg-blue-400', 'packageKey' => 'intranet', 'isSystem' => true],
                    ['libelle' => 'Formations', 'icon' => 'pi pi-graduation-cap', 'color' => 'bg-blue-400', 'packageKey' => 'intranet', 'isSystem' => true],
                ],
            ],
            [
                'libelle' => 'Finance & Comptabilité',
                'icon' => 'pi pi-wallet',
                'color' => 'bg-green-500',
                'packageKey' => 'intranet',
                'isSystem' => true,
                'children' => [
                    ['libelle' => 'Budgets Prévisionnels', 'icon' => 'pi pi-chart-line', 'color' => 'bg-green-400', 'packageKey' => 'intranet', 'isSystem' => true],
                    ['libelle' => 'Factures & Remboursements', 'icon' => 'pi pi-receipt', 'color' => 'bg-green-400', 'packageKey' => 'intranet', 'isSystem' => true],
                ],
            ],
            [
                'libelle' => 'Technique & Documentation',
                'icon' => 'pi pi-cog',
                'color' => 'bg-purple-500',
                'packageKey' => null,
                'isSystem' => false,
                'children' => [
                    ['libelle' => 'Documentation API', 'icon' => 'pi pi-code', 'color' => 'bg-purple-400', 'packageKey' => null, 'isSystem' => false],
                    ['libelle' => 'Guides Utilisateur', 'icon' => 'pi pi-book', 'color' => 'bg-purple-400', 'packageKey' => null, 'isSystem' => false],
                ],
            ],
            [
                'libelle' => 'Stages & Alternances',
                'icon' => 'pi pi-briefcase',
                'color' => 'bg-teal-500',
                'packageKey' => 'stage',
                'isSystem' => true,
                'children' => [
                    ['libelle' => 'Modèles de Convention', 'icon' => 'pi pi-pencil', 'color' => 'bg-teal-400', 'packageKey' => 'stage', 'isSystem' => true],
                    ['libelle' => 'Fiches d\'Offres de Stage', 'icon' => 'pi pi-file', 'color' => 'bg-teal-400', 'packageKey' => 'stage', 'isSystem' => true],
                ],
            ],
        ];

        $documentTypes = ['pdf', 'excel', 'word', 'powerpoint', 'image', 'video', 'audio', 'text', 'archive'];
        $titles = [
            'Rapport Annuel 2024', 'Guide d\'Accueil Étudiant', 'Contrat de Travail Type',
            'Présentation Institutionnelle', 'Analyse Budgétaire MMI', 'Documentation Technique API',
            'Procédure Qualité & Sécurité', 'Budget Prévisionnel 2025', 'Cahier des Charges Projet',
            'Manuel d\'utilisation Portail', 'Guide de Rédaction Mémoire', 'Chartes Informatique IUT',
            'Modèle de Convention de Stage', 'Guide de Recherche de Stage', 'Fiche d\'Offre Développeur Web'
        ];
        $authors = ['Marie Dubois', 'Pierre Martin', 'Sophie Leroy', 'Jean Dupont', 'Claire Bernard', 'Service Scolarité'];
        $tagsList = ['important', 'urgent', 'validé', 'stage', 'RH', 'brouillon', 'public', 'finance'];

        $createdCategories = [];

        foreach ($categoriesData as $catData) {
            $parentCat = new DocumentCategory();
            $parentCat->setLibelle($catData['libelle'])
                ->setIcon($catData['icon'])
                ->setColor($catData['color'])
                ->setPackageKey($catData['packageKey'])
                ->setIsSystem($catData['isSystem']);

            $manager->persist($parentCat);
            $createdCategories[] = $parentCat;

            foreach ($catData['children'] as $childData) {
                $childCat = new DocumentCategory();
                $childCat->setLibelle($childData['libelle'])
                    ->setIcon($childData['icon'])
                    ->setColor($childData['color'])
                    ->setPackageKey($childData['packageKey'])
                    ->setIsSystem($childData['isSystem'])
                    ->setParent($parentCat);

                $manager->persist($childCat);
                $createdCategories[] = $childCat;
            }
        }

        $docIndex = 1;
        foreach ($createdCategories as $category) {
            $numDocs = rand(2, 5);
            for ($i = 0; $i < $numDocs; $i++) {
                $type = $documentTypes[array_rand($documentTypes)];
                $title = $titles[array_rand($titles)] . ' (' . $docIndex . ')';
                $author = $authors[array_rand($authors)];
                $size = rand(10240, 15728640);
                $tags = array_intersect_key($tagsList, array_flip((array) array_rand($tagsList, rand(1, 3))));

                $doc = new Document();
                $doc->setTitre($title)
                    ->setDescription('Document d\'exemple pour la catégorie ' . $category->getLibelle())
                    ->setFilename('document_' . $docIndex . '.' . ($type === 'excel' ? 'xlsx' : ($type === 'word' ? 'docx' : $type)))
                    ->setMimeType($type === 'pdf' ? 'application/pdf' : 'application/octet-stream')
                    ->setFileSize($size)
                    ->setType($type)
                    ->setAuthor($author)
                    ->setVersion('v' . rand(1, 3) . '.' . rand(0, 9))
                    ->setTags(array_values($tags))
                    ->setVisibility('PUBLIC')
                    ->setCategory($category);

                $manager->persist($doc);
                $docIndex++;
            }
        }

        $this->chargerSupportsDeCours($manager);

        $manager->flush();
    }

    /**
     * Supports rattachés aux matières et à la SAÉ du S1 de StructureEnseignementFixtures, pour le
     * classement par matière. R1.03, R1.05 et R1.10 restent sans document.
     */
    private function chargerSupportsDeCours(ObjectManager $manager): void
    {
        // [code de l'enseignement, titre, type]
        $supports = [
            ['R1.01', 'Anglais : vocabulaire du numérique', 'pdf'],
            ['R1.01', 'Anglais : exercices de compréhension orale', 'audio'],
            ['R1.02', 'Culture numérique : histoire du web', 'powerpoint'],
            ['R1.06', 'Production graphique : charte et gabarits', 'archive'],
            ['R1.11', 'Développement web : TP formulaires', 'pdf'],
            ['R1.11', 'Développement web : corrigé du TP intégration', 'archive'],
            ['SAE1.01', 'SAÉ 1.01 : sujet et attendus', 'pdf'],
            ['SAE1.01', 'SAÉ 1.01 : grille d\'évaluation de la soutenance', 'excel'],
        ];

        $repository = $manager->getRepository(ScolEnseignement::class);
        foreach ($supports as $index => [$code, $titre, $type]) {
            $enseignement = $repository->findOneBy(['codeEnseignement' => $code]);
            if (null === $enseignement) {
                throw new \RuntimeException(sprintf('Enseignement %s introuvable.', $code));
            }

            $doc = new Document();
            $doc->setTitre($titre)
                ->setFilename('support_' . ($index + 1) . '.' . match ($type) {
                    'excel' => 'xlsx',
                    'powerpoint' => 'pptx',
                    'audio' => 'mp3',
                    'archive' => 'zip',
                    default => 'pdf',
                })
                ->setMimeType($type === 'pdf' ? 'application/pdf' : 'application/octet-stream')
                ->setFileSize(rand(10240, 5242880))
                ->setType($type)
                ->setAuthor('Équipe pédagogique MMI')
                ->setVisibility('ETUDIANT')
                ->setEnseignement($enseignement);

            $manager->persist($doc);
        }
    }
}
