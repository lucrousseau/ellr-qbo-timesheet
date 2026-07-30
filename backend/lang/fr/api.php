<?php

/**
 * French API response messages for JSON endpoints.
 */

return [
    'logged_out' => 'Déconnexion réussie.',
    'registration_disabled' => 'Inscription désactivée.',
    'invalid_credentials' => 'Identifiants invalides.',
    'admin_required' => 'Accès administrateur requis.',
    'organization_required' => 'Une organisation est requise pour ce compte.',
    'email_not_verified' => 'L\'adresse courriel n\'est pas vérifiée.',
    'quickbooks_not_connected' => 'QuickBooks n\'est pas connecté.',
    'quickbooks_expired' => 'La connexion QuickBooks a expiré. Reconnectez-la depuis l\'application admin.',
    'quickbooks_busy' => 'QuickBooks est occupé. Réessayez sous peu.',
    'quickbooks_api_error' => 'Erreur API QuickBooks',
    'qbo_employee_not_configured' => 'L\'employé QBO n\'est pas configuré pour cet utilisateur.',
    'qbo_employee_not_found' => 'Employé QuickBooks introuvable.',
    'qbo_employee_removed' => 'Votre accès employé QuickBooks a été retiré. Contactez un administrateur.',
    'qbo_employee_email_missing' => 'L\'employé QuickBooks n\'a pas d\'adresse courriel configurée.',
    'qbo_employee_email_conflict' => 'Le courriel de l\'employé QuickBooks est déjà utilisé par un autre compte.',
    'admin_invalid_customer_assignment' => 'Un ou plusieurs clients sélectionnés ne sont pas disponibles dans QuickBooks.',
    'time_activity_not_found' => 'Activité de temps introuvable',
    'time_tracker_empty' => 'Aucune session de minuterie active à enregistrer.',
    'time_tracker_no_elapsed_time' => 'La minuterie n\'a pas de temps écoulé à enregistrer.',
    'time_tracker_invalid_customer' => 'Le client sélectionné n\'est pas disponible pour votre employé QuickBooks.',
    'time_tracker_invalid_project' => 'Le projet sélectionné n\'est pas disponible pour le client choisi.',
    'time_tracker_invalid_service' => 'Le service sélectionné n\'est pas disponible dans QuickBooks.',
    'password_reset_sent' => 'Si ce courriel existe, un lien de réinitialisation a été envoyé.',
    'password_reset_success' => 'Mot de passe réinitialisé avec succès.',
    'password_changed' => 'Mot de passe mis à jour avec succès.',
    'email_already_verified' => 'Le courriel est déjà vérifié.',
    'verification_link_sent' => 'Lien de vérification envoyé.',
    'time' => [
        'end_after_start' => 'L\'heure de fin doit être postérieure à l\'heure de début.',
        'incomplete_fields' => 'Les heures de début et de fin sont requises lors de la mise à jour des champs horaires.',
    ],
];
