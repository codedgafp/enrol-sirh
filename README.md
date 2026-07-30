# Reprise des données SIRH

Ce script permet de lancer la reprise complète des données de suivi SIRH afin de mettre à jour les informations de synchronisation dans la table `session_mentor` de mentor-api (SIRH).

## Exécution

Depuis la racine du plugin `enrol/sirh` :

```bash
php cli/update_all_send_session_followup_information.php

# en background
nohup php cli/update_all_send_session_followup_information.php \
  > update_all_send_session_followup_information.log \
  2>&1 &
```