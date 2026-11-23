<?php

namespace QuestionnaireBundle\Email;

use App\Service\Email\AbstractEmailDefinition;

/**
 * Envoyé aux participants qui n'ont pas encore envoyé leurs réponses (relance manuelle de l'équipe ou relance automatique).
 * Contexte fourni par QuestionnaireMailer (valeurs simples).
 */
class QuestionnaireReminderEmail extends AbstractEmailDefinition
{
    public function getKey(): string
    {
        return 'questionnaire.reminder';
    }

    public function getLabel(): string
    {
        return 'Rappel de questionnaire en attente';
    }

    public function getDefaultSubject(): string
    {
        return '{% if customSubject is defined and customSubject %}{{ customSubject }}{% else %}Rappel : Répondre au questionnaire : {{ questionnaire.title }}{% endif %}';
    }

    public function getHtmlTemplatePath(): string
    {
        return '@Questionnaire/emails/questionnaire/reminder.html.twig';
    }

    public function getTxtTemplatePath(): string
    {
        return '@Questionnaire/emails/questionnaire/reminder.txt.twig';
    }

    public function getAvailableVariables(): array
    {
        return [
            'invitation'    => 'Objet QuestionnaireInvitation (token, email)',
            'questionnaire' => 'Objet Questionnaire (titre, description)',
            'surveyUrl'     => 'URL directe pour accéder au questionnaire',
            'expiresAt'     => 'Date de fermeture du questionnaire (si définie)',
            'customSubject' => 'Objet saisi lors d\'une relance manuelle',
            'customMessage' => 'Message saisi lors d\'une relance manuelle',
        ];
    }

    public function getDescription(): string
    {
        return 'Envoyé pour rappeler aux participants de répondre à un questionnaire.';
    }
}
