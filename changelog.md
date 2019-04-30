# Change Log
Contributors: brouardt  
Author URI: https://github.com/brouardt/paralog  

## [1.6.11] - 2019-04-30
# Modification
- Tri de la vue des présences par date décroissante (défaut) 

## [1.6.10] - 2019-04-30
## Modification
- Arrangement de code

## [1.6.9] - 2019-04-30
## Ajout
- Ajout d'un bouton nouveau décollage

## [1.6.8] - 2019-04-30
## Correction
- Affichage de la vue des présences groupées par date

## [1.6.7] - 2019-04-30
## Correction
- Envoi e-mails multiple
## Modification
- Traduction

## [1.6.6] - 2019-04-29
## Ajout
- Prise en compte d'une date de rappel pour l'envoi manuel.

## [1.6.5] - 2019-04-26
## Ajout
- Gestion des appels de classes avec spl_autoloader_register()

## [1.6.1] - 2019-04-19
## Ajout
- Gestion des réglages
## Modification
- Élimination des shortcodes php
- Remplacement des die() en wp_die()

## [1.6.0] - 2019-04-15
## Ajout
- Gestion des abonnements de relance des pilotes, treuilleurs, élèves et moniteurs
## Modification
- Ajout des emails et bit d'abonnement aux rappels de présence

## [1.5.1] - 2019-04-14
## Ajout
- Création de la gestion de qui et quand sera présent
## Modification
- Protection des tables et champs

## [1.4.9] - 2019-03-28
## Ajout
- Création de la gestion des activités
- Nouvelle structure de base de données

## [1.3.11] - 2018-09-02
## Modification
- Modification de la gestion des définitions de cookies

## [1.3.10] - 2018-08-25
## Ajout
- Saisie de la date et de l'heure de décollage / treuillage désormais possible.

## [1.3.9] - 2018-04-28
## Correction
- Correction d'un bug du calcul statistique du nombre de passagers sur sites.

## [1.3.8] - 2018-04-28
## Correction
- Ajout du filtre deleted = 0 dans les requêtes nécessitant sont usage.

## [1.3.7] - 2018-04-27
## Modification
- Gestion des suppressions douces. Celles-ci seront seulement dans un état deleted avant 
que l'administrateur ne les supprime définitivement

## [1.3.6] - 2018-04-22
## Modfication
- Prise en compte des auteurs de documents (site, ligne, personnel, décollage). Ceux-ci pourront désormais modifier ou supprimer leurs documents.

## [1.3.5] - 2018-03-22
## Modification
- Prise en compte du format réel de la date et heure définie dans wordpress
## Suppression
- Retrait des traductions du format de date et heure

## [1.3.4] - 2018-03-15
## Modification
- Retrait de la colonne user_id dans l'exportation des données

## [1.3.3] - 2018-03-15
## Modification
- Changements dans les textes et traductions.

## [1.3.2] - 2018-03-12
## Ajout
- Dans la vue Personne, le numéro de licence est lié au site de la FFVL.

## [1.3.1] - 2018-03-11
## Ajout
- Insertion du user_id actif dans les tables de sauvegarde.

## [1.2.9] - 2018-03-11
### Ajout
- Création d'un cookie pour memorisation temporaire (durée: 12H) du site et de la ligne de vol. Afin d'éviter toute saisies superflues dans le journal de log, lorsque l'on se trouve toute la journée sur le même site.

## [1.2.8] - 2018-03-06
### Modification
- Exportation des données par dates de décollage décroissantes

## [1.2.7] - 2018-03-04
### Modification
- Amélioration de l'export. Extraction des noms de colonnes de table automatique et exportation sequentielle.

## [1.2.4] - 2018-03-03
### Ajout
- Exportation des données brutes depuis la page "A propos de"

## [1.2.3] - 2018-03-01
### Modification
- La première colonne des personnes regroupe désormais le prénom et le nom. Ce qui rend plus lisible les pilotes sur écran de téléphone
- Suppression de la visualisation de la colonne n° vol

## [1.2.1] - 2018-02-25
### Modification
- La première colonne des journaux de décollage devient la date et heure de décollage

## [1.1.7] - 2018-02-19
### Correction
- La gestion de la sauvegarde du champ "passenger_name" type VARCHAR(129) DEFAULT NULL ne prenait pas correctement en compte la valeur (vide).

## [1.1] - 2018-01-02
### Ajout
- formulaire (ajout et modification) de line, personne et journaux de treuillé.

## [1.0.135] - 2018-01-01
### Ajout
- formulaire (ajout et modification) de site

## [1.0.108] - 2017-12-30
### Ajout
- admin menu (A propos de)
- statistiques des éléments des tables wl-...
- visualisation des éléments sites, lignes, personnes, treuillés

## [1.0.60] - 2017-12-01
## Ajout
- L'extention peut être installée, désactivée, désinstallée
- Écriture des paramètres : db_version, active/inactive, version

## [1.0.0] - 2017-11-30
### Ajout
- Création du squelette de l'application en mode Objet.
