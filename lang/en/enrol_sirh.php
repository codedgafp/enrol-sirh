<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Strings for component 'enrol_sirh', language 'en'.
 *
 * @package    enrol_sirh
 * @copyright  2022 Edunao SAS (contact@edunao.com)
 * @author     remi <remi.colet@edunao.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['privacy:metadata'] = 'This plugin does not store any user data';
$string['addgroup'] = 'Add to group';
$string['assignrole'] = 'Assign role';
$string['sirh:config'] = 'Configure sirh instances';
$string['sirh:unenrol'] = 'Unenrol suspended users';
$string['defaultgroupnametext'] = '{$a->name} sirh {$a->increment}';
$string['instanceexists'] = 'Sirh is already synchronised with selected role';
$string['pluginname'] = 'Inscriptions SIRH';
$string['pluginname_desc'] = 'SIRH enrolment plugin synchronises with SIRH api.';
$string['instancepluginname'] = 'Inscription SIRH';
$string['status'] = 'Active';
$string['pluginname_desc'] = 'SIRH enrolment plugin synchronises with SIRH api.';
$string['langfile'] = 'French';
$string['servicenotavailable'] = 'Service SIRH is not available.';

// Config instance form.
$string['sirhlabel'] = 'Identifiant du SIRH d\'origine';
$string['sirhtraininglabel'] = 'Identifiant de la formation';
$string['sirhsessionlabel'] = 'Identifiant de la session';

// Template.
$string['filterlabeltemplate'] = 'Filtrer par';
$string['sirhlabelfiltertemplate'] = 'SIRH';
$string['sirhtrainingidlabelfiltertemplate'] = 'ID de formation';
$string['sirhtraininglabelfiltertemplate'] = 'Libellé de la formation';
$string['sirhsessionidlabelfiltertemplate'] = 'ID de session';
$string['sirhsessionlabelfiltertemplate'] = 'Libellé de la session';
$string['sirhdatestartlabelfiltertemplate'] = 'Date de début';
$string['sirhdateendlabelfiltertemplate'] = 'Date de fin';
$string['applyfiltertemplate'] = 'Appliquer les filtres';
$string['resetfiltertemplate'] = 'Réinitialiser les filtres';

$string['sirhtrainingidtitletemplate'] = 'ID de formation';
$string['sirhtrainingtitletemplate'] = 'Libellé de la formation';
$string['sirhsessionidtitletemplate'] = 'ID de session';
$string['sirhsessiontitletemplate'] = 'Libellé de la session';
$string['sirhdatestarttitletemplate'] = 'Date de début';
$string['sirhdateendtitletemplate'] = 'Date de fin';
$string['actiontitletemplate'] = 'Action';

// Js variables.
$string['reload'] = 'Réactualiser';
$string['viewenrol'] = 'Visualiser/Inscrire';
$string['submit'] = 'Valider';
$string['cancel'] = 'Annuler';
$string['enrolpopuptitle'] = 'Inscrire des utilisateurs d\'un SIRH';
$string['confirmmessage']
    = 'Confirmez-vous l’inscription des {$a->nbTotalUsers} utilisateurs en provenance du SIRH {$a->sirh} pour la formation {$a->sirhtraining}, à la session {$a->sirhsession} ?';
$string['previewusers'] = 'Visualisation des {$a} premiers utilisateurs :';

$string['syncsirh'] = 'Liaison SIRH';
$string['syncsirhtitle']
    = 'Inscriptions des utilisateurs en provenance du SIRH {$a->sirh} pour la formation {$a->sirhtraining}, à la session {$a->sirhsession}';
$string['addtogroup'] = 'Ajouter au groupe';
$string['addtonogroup'] = 'Aucun';
$string['addtonewgroup'] = 'Créer un groupe';
$string['addtoexistinggroup'] = 'La liste des groupes déjà existants pour la session';
$string['continuesync'] = 'Poursuivre la liaison';
$string['savesync'] = 'Enregistrer la liaison';
$string['preview_report'] = 'Rapport de prévisualisation';
$string['identified_users'] = 'Nombre d\'utilisateurs identifiés : {$a}';
$string['account_creation_number'] = 'Nombre de création de comptes : {$a}';
$string['account_reactivation_number'] = 'Nombre de comptes réactivés : {$a}';
$string['notification_sirh_success'] = 'Liaison SIRH effectuée.';
$string['errors_number'] = 'Nombre d\'erreurs : {$a}';
$string['warnings_number'] = 'Nombre d\'avertissements : {$a}';
$string['errors_detected'] = 'Des anomalies ont été détectées.';
$string['warnings_detected'] = 'Des avertissements ont été détectés.';
$string['warning'] = 'Avertissement';
$string['warning_create_group'] = 'Attention, le groupe {$a->sirh} - {$a->sirhtraining} - {$a->sirhsession} sera créé.';
$string['warning_nbusers']
    = 'Attention, le nombre maximal de participants pour cette session va être dépassé de {$a}.';
$string['warning_user_role']
    = 'Pour l\'utilisateur {$a->mail} - Attention, l\'utilisateur est déjà inscrit en tant que {$a->oldrole}. Il sera maintenant inscrit en tant que {$a->newrole}.';
$string['warning_unsuspend_user'] = 'Pour l\'utilisateur {$a} - Le compte utilisateur est suspendu. Le compte sera réactivé.';
$string['error_specials_chars']
    = 'Pour l\'utilisateur {$a} - Des caractères spéciaux sont présents. Cette ligne sera ignorée à l\'enregistrement de la liaison.';
$string['error_email_not_valid']
    = 'Pour l\'utilisateur {$a} - Le format de l\'adresse de courriel est incorrect. Cette ligne sera ignorée à l\'enregistrement de la liaison.';
$string['error_user_role']
    = 'Pour l\'utilisateur {$a->mail} - L\'utilisateur est inscrit en tant que {$a->role}. Afin que l\'utilisateur ne perde pas son rôle, cette ligne sera ignorée à l\'enregistrement de la liaison.';
$string['showmore'] = 'Lire la suite';
$string['showless'] = 'Voir moins';
$string['sirh_modal_title'] = 'Notification des nouveaux utilisateurs';
$string['sirh_modal_content']
    = 'Attention, une notification mél sera envoyée à tous les utilisateurs inscrits à la session. Les utilisateurs ayant un compte créé ou réactivé recevront également une notification spécifique.

Cette opération peut prendre un certain temps selon le volume d\'inscription à traiter, vous recevrez une confirmation par mél dès que l\'opération sera terminée.
Voulez-vous continuer ?';
$string['sirh_group_name'] = 'Liaison SIRH - {$a->c1} - {$a->c2} - {$a->c3}';

$string['task_check_update_sirh'] = 'Mettre à jour les liaisons SIRH/Mentor';
$string['task_send_session_followup_information'] = 'Transmettre les informations de suivi des sessions de formation';

$string['upate_data_sirh_subject'] = 'Mentor : Modification des informations d\'une session SIRH';
$string['upate_data_sirh_email'] = 'Bonjour,

Les informations en provenance du SIRH {$a->sirh} pour la formation {$a->trainingsirh}, à la session {$a->sessionsirh} ont été modifiées par le SIRH.

Voici les nouvelles informations relatives à cette session :

Libellé de la formation : {$a->nametrainingsirh}
Libellé de la session : {$a->namesessionsirh}
Date de début de la session : {$a->startdate}
Date de fin de la session : {$a->enddate}
Si ces informations nécessitent une modification sur Mentor, il vous appartient de les effectuer dans la session : <a href="{$a->sessionurl}">{$a->sessionurl}</a>


Pour tout renseignement, veuillez vous rapprocher du SIRH concerné.

L\'équipe du programme Mentor';

$string['upate_user_sirh_subject'] = 'Mentor : Modification des inscriptions à une session Mentor par un SIRH';
$string['upate_user_sirh_email'] = 'Bonjour,

Les inscriptions à la session Mentor {$a->sessionname} en liaison avec la session en provenance du SIRH {$a->sirh} pour la formation {$a->trainingsirh}, à la session {$a->sessionsirh} ont été modifiées par le SIRH.

Vous pouvez dès à présent consulter les informations relatives aux inscriptions de cette session dans l\'application Mentor dans la gestion de votre session : <a href="{$a->sessionurl}">{$a->sessionurl}</a>


Pour tout renseignement complémentaire, veuillez vous rapprocher du SIRH concerné.

L\'équipe du programme Mentor';
$string['error_task_specials_chars']
    = 'Pour la session en provenance du SIRH {$a->sirh} pour la formation {$a->trainingsirh}, à la session {$a->sessionsirh} et l\'utilisateur {$a->useremail} - Des caractères spéciaux sont présents. Cette ligne a été ignorée à l\'enregistrement de la liaison.';
$string['error_task_email_not_valid']
    = 'Pour la session en provenance du SIRH {$a->sirh} pour la formation {$a->trainingsirh}, à la session {$a->sessionsirh} et l\'utilisateur {$a->useremail} - Le format de l\'adresse de courriel est incorrect. Cette ligne a été ignorée à l\'enregistrement de la liaison.';
$string['error_task_user_role']
    = 'Pour l\'utilisateur {$a->mail} - L\'utilisateur est inscrit en tant que {$a->role}. Afin que l\'utilisateur ne perde pas son rôle, cette ligne a été ignorée à l\'enregistrement de la liaison.';
