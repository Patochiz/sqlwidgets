# SQL Widgets — Module Dolibarr

**Auteur :** S2IRD  
**Site :** https://www.s2ird.fr  
**Version :** 1.0.0  
**Compatibilité :** Dolibarr 14+ / PHP 7.2+  
**Licence :** GPLv3  

---

## Description

SQL Widgets permet de créer des widgets entièrement personnalisés sur le tableau de bord de Dolibarr, alimentés par vos propres requêtes SQL.

Trois types de widgets sont disponibles :

- **Chiffre** — Un pavé de KPI avec valeur principale, deux sous-chiffres optionnels, icône et couleurs personnalisables. Affiché directement dans la zone de statistiques de la page d'accueil.
- **Tableau** — Un tableau de données avec colonnes configurables, badges de statut colorés, liens cliquables et formatage des types de données.
- **Graphique** — Un graphique en barres, courbes ou anneaux rendu via Chart.js, avec palette de couleurs personnalisable.

Les widgets sont sécurisés par un système de droits basé sur les groupes d'utilisateurs Dolibarr : chaque widget peut être rendu accessible à un ou plusieurs groupes spécifiques.

---

## Prérequis

- Dolibarr **14.0** ou supérieur
- PHP **7.2** ou supérieur
- MySQL / MariaDB
- Droits d'écriture sur le répertoire `htdocs/custom/sqlwidgets/core/boxes/` (génération automatique des fichiers de box)

---

## Installation

1. Télécharger l'archive `sqlwidgets.zip`
2. Décompresser dans `htdocs/custom/` de votre installation Dolibarr
3. La structure doit être : `htdocs/custom/sqlwidgets/`
4. Aller dans **Configuration → Modules → Autres**
5. Activer le module **SQL Widgets**
6. Les tables de base de données sont créées automatiquement à l'activation

---

## Structure des fichiers

```
htdocs/custom/sqlwidgets/
├── admin/
│   ├── setup.php              Page de configuration du module
│   ├── widget_list.php        Liste et gestion des widgets
│   ├── widget_edit.php        Création / édition d'un widget
│   └── widget_access.php      Gestion des droits par groupe
├── ajax/
│   └── widget_data.php        Endpoint AJAX — retourne les données en JSON
├── class/
│   ├── sqlwidget.class.php            Classe principale (CRUD widgets)
│   ├── sqlwidget_boxmanager.class.php Gestion des fichiers de box dynamiques
│   └── actions_sqlwidgets.class.php   Hook dashboard (widgets chiffres)
├── core/
│   ├── boxes/
│   │   └── box_sqlwidget_base.php     Classe de base des boxes Dolibarr
│   └── modules/
│       └── modSqlWidgets.class.php    Descripteur du module
├── langs/
│   ├── fr_FR/sqlwidgets.lang
│   └── en_US/sqlwidgets.lang
└── sql/
    ├── llx_sqlwidgets_widget.sql
    └── llx_sqlwidgets_widget_access.sql
```

---

## Utilisation

### 1. Créer un widget

Aller dans le menu **SQL Widgets → Gestion widgets → Nouveau widget**.

Renseigner :
- **Référence** — Identifiant unique (ex: `nb_commandes_mois`)
- **Libellé** — Nom affiché dans le dashboard
- **Type** — Chiffre, Tableau ou Graphique
- **Requête SQL** — La requête SELECT qui alimente le widget
- **Couleurs** — Personnalisation visuelle complète
- **Options** — Selon le type (voir ci-dessous)

### 2. Gérer les droits d'accès

Aller dans **SQL Widgets → Droits d'accès**.

Sélectionner un widget puis cocher les groupes d'utilisateurs autorisés à le voir.  
Les administrateurs voient toujours tous les widgets, quelle que soit la configuration.

### 3. Afficher les widgets sur le tableau de bord

- Les widgets **Chiffre** apparaissent automatiquement dans la zone de statistiques de la page d'accueil, sans aucune configuration supplémentaire.
- Les widgets **Tableau** et **Graphique** apparaissent en tant que boxes Dolibarr. Aller sur la page d'accueil, cliquer sur **"Configurer ma page"**, puis glisser les boxes souhaitées dans une colonne.

---

## Configuration des widgets

### Type Chiffre

La requête SQL doit retourner **une seule valeur** sur la première ligne, première colonne.

```sql
-- Exemple : nombre de commandes du mois en cours
SELECT COUNT(*) 
FROM llx_commande 
WHERE MONTH(date_commande) = MONTH(NOW()) 
  AND YEAR(date_commande) = YEAR(NOW())
  AND fk_statut > 0
```

**Options disponibles :**

| Option | Description | Exemple |
|--------|-------------|---------|
| Icône | Picto Dolibarr, classe FontAwesome ou emoji | `order` / `fa-shopping-cart` / `📦` |
| Lien | URL de destination au clic sur le pavé | `/dolibarr/commande/list.php` |
| Préfixe | Texte avant la valeur | `€` |
| Suffixe | Texte après la valeur | `%` |
| SQL sous-chiffre 1 | Requête pour la valeur secondaire | `SELECT SUM(total_ht) FROM ...` |
| Libellé sous-chiffre 1 | Label de la valeur secondaire | `Ce mois` |
| SQL sous-chiffre 2 | Requête pour la troisième valeur | `SELECT COUNT(*) FROM ...` |
| Libellé sous-chiffre 2 | Label de la troisième valeur | `En attente` |

**Icônes supportées :**
- Pictos natifs Dolibarr : `order`, `bill`, `contract`, `user`, `product`, `stats`, `invoice`...
- FontAwesome 5/6 : `fa-chart-bar`, `fas fa-shopping-cart`, `far fa-calendar`...
- Emoji : `📦`, `📊`, `💰`, `🛒`, `📈`...

---

### Type Tableau

La requête SQL peut retourner **autant de lignes et colonnes** que souhaité.

```sql
-- Exemple : dernières commandes avec statut
SELECT 
    c.ref          AS reference,
    s.nom          AS client,
    c.total_ht     AS montant,
    c.fk_statut    AS statut,
    c.date_commande AS date
FROM llx_commande c
INNER JOIN llx_societe s ON s.rowid = c.fk_soc
WHERE c.entity = 1
ORDER BY c.date_commande DESC
LIMIT 20
```

**Configuration des colonnes :**

Chaque colonne se configure indépendamment :

| Paramètre | Description | Exemple |
|-----------|-------------|---------|
| Champ SQL | Nom de la colonne retournée par la requête | `montant` |
| Libellé | En-tête de la colonne affichée | `Montant HT` |
| Type donnée | Format d'affichage (voir tableau ci-dessous) | `price` |
| Lien | URL cliquable avec variables `{champ}` | `/dolibarr/commande/card.php?id={rowid}` |
| Classe CSS | Classes Dolibarr appliquées à la cellule | `nowraponall right` |
| Badges JSON | Correspondance valeur → pastille colorée | `{"0":{"label":"Brouillon","color":"#999"},"1":{"label":"Validé","color":"#10B981"}}` |

**Types de données disponibles :**

| Type | Affichage | Exemple |
|------|-----------|---------|
| `varchar` | Texte brut, aucune transformation | `Dupont SARL` |
| `int` | Entier avec séparateur de milliers | `1 234` |
| `numeric` | Décimal 2 chiffres, séparateurs locaux | `1 234,56` |
| `price` | Prix avec devise Dolibarr (`price()`) | `1 234,56 €` |
| `pourcent` | Pourcentage avec symbole % | `12,50 %` |

> Le formatage respecte automatiquement la locale et la devise configurées dans Dolibarr.

**Exemple de configuration Badges JSON :**
```json
{
    "0": {"label": "Brouillon",  "color": "#94A3B8"},
    "1": {"label": "Validé",     "color": "#10B981"},
    "2": {"label": "Expédié",    "color": "#3B82F6"},
    "3": {"label": "Facturé",    "color": "#8B5CF6"},
    "-1": {"label": "Annulé",   "color": "#EF4444"}
}
```

---

### Type Graphique

La requête SQL doit retourner :
- **Première colonne** → Labels de l'axe X (texte)
- **Colonnes suivantes** → Séries de données (valeurs numériques)

```sql
-- Exemple : chiffre d'affaires par mois sur les 12 derniers mois
SELECT 
    DATE_FORMAT(date_commande, '%Y-%m') AS mois,
    SUM(total_ht)                        AS ca_ht,
    COUNT(*)                             AS nb_commandes
FROM llx_commande
WHERE fk_statut > 0
  AND date_commande >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
GROUP BY DATE_FORMAT(date_commande, '%Y-%m')
ORDER BY mois ASC
```

**Types de graphiques :**
- `bar` — Barres verticales
- `line` — Courbes
- `doughnut` — Anneaux

**Couleurs des séries :** saisir une liste de codes hexadécimaux séparés par des virgules.  
Exemple : `#3B82F6,#10B981,#F59E0B,#EF4444`

---

## Sécurité

### Requêtes SQL

Seules les requêtes `SELECT` et `WITH ... SELECT` sont autorisées.  
Les instructions suivantes sont bloquées : `DROP`, `DELETE`, `UPDATE`, `INSERT`, `ALTER`, `CREATE`, `TRUNCATE`, `EXEC`, `EXECUTE`.

Un bouton **"Tester la requête SQL"** est disponible dans le formulaire d'édition pour valider votre requête avant enregistrement.

### Droits d'accès

| Droit | Description | Défaut |
|-------|-------------|--------|
| `read` | Voir les widgets | Activé pour tous |
| `write` | Créer et modifier des widgets | Désactivé |
| `delete` | Supprimer des widgets | Désactivé |
| `manage_access` | Gérer les droits par groupe | Désactivé |

---

## Base de données

Le module crée deux tables à l'activation :

**`llx_sqlwidgets_widget`** — Définition des widgets

| Colonne | Type | Description |
|---------|------|-------------|
| `rowid` | int | Clé primaire |
| `entity` | int | Entité Dolibarr (multi-société) |
| `ref` | varchar(128) | Référence unique |
| `label` | varchar(255) | Libellé affiché |
| `widget_type` | varchar(32) | `number`, `table` ou `chart` |
| `datasource` | longtext | Requête SQL principale |
| `color_*` | varchar(32) | Couleurs personnalisées |
| `chart_type` | varchar(32) | `bar`, `line` ou `doughnut` |
| `type_options` | longtext | Options JSON (colonnes, icône, sous-SQL...) |
| `position` | int | Ordre d'affichage |
| `active` | tinyint | Actif / inactif |

**`llx_sqlwidgets_widget_access`** — Droits d'accès par groupe

| Colonne | Type | Description |
|---------|------|-------------|
| `rowid` | int | Clé primaire |
| `fk_widget` | int | Référence au widget |
| `fk_group` | int | Référence au groupe utilisateur |

---

## Exemples de requêtes SQL

### Chiffres

```sql
-- Nombre de clients actifs
SELECT COUNT(*) FROM llx_societe WHERE client = 1 AND status = 1 AND entity = 1

-- Chiffre d'affaires du mois (commandes validées)
SELECT COALESCE(SUM(total_ht), 0)
FROM llx_commande
WHERE fk_statut >= 1
  AND MONTH(date_commande) = MONTH(NOW())
  AND YEAR(date_commande) = YEAR(NOW())
  AND entity = 1

-- Factures impayées (montant total)
SELECT COALESCE(SUM(total_ttc - paye), 0)
FROM llx_facture
WHERE fk_statut = 1 AND paye = 0 AND entity = 1

-- Taux de transformation commandes/devis (%)
SELECT ROUND(
    COUNT(DISTINCT c.rowid) * 100.0 / NULLIF(COUNT(DISTINCT p.rowid), 0)
, 1)
FROM llx_propal p
LEFT JOIN llx_commande c ON c.fk_source = p.rowid
WHERE p.entity = 1
  AND YEAR(p.date_valid) = YEAR(NOW())
```

### Tableaux

```sql
-- Top 10 clients par CA sur 12 mois
SELECT 
    s.nom            AS client,
    COUNT(c.rowid)   AS nb_commandes,
    SUM(c.total_ht)  AS ca_ht
FROM llx_commande c
INNER JOIN llx_societe s ON s.rowid = c.fk_soc
WHERE c.fk_statut >= 1
  AND c.date_commande >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
  AND c.entity = 1
GROUP BY s.rowid, s.nom
ORDER BY ca_ht DESC
LIMIT 10

-- Articles en rupture de stock
SELECT 
    p.ref        AS reference,
    p.label      AS designation,
    ps.reel      AS stock_reel,
    ps.seuil_stock_alerte AS seuil
FROM llx_product p
INNER JOIN llx_product_stock ps ON ps.fk_product = p.rowid
WHERE p.fk_product_type = 0
  AND ps.reel <= ps.seuil_stock_alerte
  AND p.entity = 1
ORDER BY ps.reel ASC
```

### Graphiques

```sql
-- Évolution du CA mensuel sur 12 mois
SELECT 
    DATE_FORMAT(date_commande, '%b %Y') AS mois,
    ROUND(SUM(total_ht), 2)             AS ca_ht
FROM llx_commande
WHERE fk_statut >= 1
  AND date_commande >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
  AND entity = 1
GROUP BY DATE_FORMAT(date_commande, '%Y-%m')
ORDER BY MIN(date_commande) ASC

-- Répartition des commandes par statut
SELECT 
    CASE fk_statut
        WHEN 0  THEN 'Brouillon'
        WHEN 1  THEN 'Validé'
        WHEN 2  THEN 'Expédié'
        WHEN 3  THEN 'Livré'
        WHEN -1 THEN 'Annulé'
        ELSE 'Autre'
    END AS statut,
    COUNT(*) AS nb
FROM llx_commande
WHERE entity = 1
GROUP BY fk_statut
ORDER BY fk_statut
```

---

## Dépendances externes

| Librairie | Version | Usage | Chargement |
|-----------|---------|-------|------------|
| [Chart.js](https://www.chartjs.org/) | 4.4.0 | Rendu des graphiques | CDN jsDelivr (uniquement pour les widgets graphique) |

---

## ChangeLog

### v1.0.0
- Version initiale
- Widgets de type Chiffre, Tableau et Graphique
- Droits d'accès par groupe utilisateur
- Formatage des données : varchar, int, numeric, price, pourcentage
- Icônes : pictos Dolibarr, FontAwesome, emoji
- Boxes Dolibarr natives avec boutons déplacer / fermer
- Calcul dynamique des URLs (indépendant de l'installation)
- Compatible Dolibarr 14+ et PHP 7.2+

---

## Support

Pour toute question ou rapport de bug, contacter **S2IRD** via [https://www.s2ird.fr](https://www.s2ird.fr).
