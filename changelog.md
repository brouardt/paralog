# Change Log
Contributors: brouardt  
Author URI: https://github.com/brouardt/paralog  

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
