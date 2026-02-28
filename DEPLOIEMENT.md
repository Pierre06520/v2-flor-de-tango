# Déploiement – Flor de Tango (inscriptions PHP)

## Prérequis

- Hébergement mutualisé avec **PHP ≥ 8.0** et **MySQL** (OVH, o2switch, Infomaniak, etc.)
- Accès FTP ou gestionnaire de fichiers
- Accès phpMyAdmin (ou MySQL en ligne de commande)

---

## 1. Configurer `config.php`

Ouvrir `config.php` et renseigner vos valeurs réelles :

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'flor_de_tango');   // nom de la base créée dans phpMyAdmin
define('DB_USER', 'flor_user');       // utilisateur MySQL
define('DB_PASS', 'VotreMotDePasse'); // mot de passe MySQL

define('ADMIN_PASSWORD', 'MotDePasseAdmin'); // mot de passe pour /admin.php
define('ADMIN_EMAIL',    'celine79.baquet@yahoo.com');
define('SITE_URL',       'https://votredomaine.fr'); // URL racine du site

define('MAIL_FROM', 'noreply@votredomaine.fr'); // expéditeur des e-mails
```

> **Important** : `MAIL_FROM` doit être une adresse du même domaine que votre hébergement,
> sinon les e-mails seront rejetés comme spam.

---

## 2. Créer la base de données

### Via phpMyAdmin
1. Créer une base `flor_de_tango` (UTF-8 / utf8mb4_unicode_ci)
2. Sélectionner la base → onglet **SQL**
3. Coller le contenu de `setup.sql` → **Exécuter**

### Via ligne de commande
```bash
mysql -u root -p < setup.sql
```

---

## 3. Uploader les fichiers

Envoyer par FTP la totalité des fichiers à la racine (ou sous-dossier) de votre hébergement :

```
/
├── index.html
├── inscription.php
├── admin.php
├── download.php
├── config.php
├── setup.sql          # peut être supprimé après import
├── uploads/
│   └── .htaccess
```

---

## 4. Permissions du dossier `uploads/`

Le dossier `uploads/` doit être **accessible en écriture** par PHP.
Via FTP, faire un clic droit → **Permissions (chmod) 750** ou `755`.

Le fichier `uploads/.htaccess` bloque déjà l'accès direct aux documents uploadés.

---

## 5. Vérifier le fonctionnement

| URL | Ce que vous devriez voir |
|-----|--------------------------|
| `https://votredomaine.fr/inscription.php` | Formulaire d'inscription |
| `https://votredomaine.fr/admin.php` | Page de connexion administration |
| `https://votredomaine.fr/uploads/monFichier.jpg` | **403 Forbidden** (fichiers protégés) |

---

## 6. Flux complet

```
Candidat remplit le formulaire
        ↓
Fichier stocké dans uploads/ (nom aléatoire)
Données enregistrées en base MySQL
        ↓
E-mail de confirmation → candidat
E-mail de notification → admin (avec lien vers /admin.php)
        ↓
Admin se connecte à admin.php
Consulte le document (bouton Télécharger)
Clique sur [Valider]
        ↓
Statut mis à jour en base
E-mail de validation → candidat
```

---

## Sécurité

- Les documents ne sont **jamais** accessibles directement par URL.
- Téléchargement uniquement via `download.php` après connexion admin.
- Noms de fichiers randomisés (hex 32 caractères) — aucun chemin devinable.
- Toutes les requêtes SQL utilisent des **requêtes préparées** (protection contre l'injection SQL).
- Le formulaire est protégé par un **jeton CSRF**.

---

## E-mails ne partent pas ?

Sur certains hébergeurs (OVH notamment), `mail()` est désactivé ou les e-mails arrivent en spam si `MAIL_FROM` ne correspond pas au domaine.

Pour une livraison fiable, remplacez `mail()` par **PHPMailer + SMTP** :

```bash
# Via Composer (si disponible sur votre hébergement)
composer require phpmailer/phpmailer
```

Voir : https://github.com/PHPMailer/PHPMailer
