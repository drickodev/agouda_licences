# Déploiement — hébergement cPanel LWS (avec accès SSH)

Guide pour déployer ce projet sur `keys.agoudatech.com`, sous-domaine créé
pour ce projet sur l'hébergement cPanel LWS. Écrit pour un accès **SSH
(Terminal)** — plus simple et plus fiable qu'un déploiement par
Gestionnaire de fichiers/FTP + Cron Jobs : Composer et Artisan tournent
directement sur le serveur, pas besoin de construire un paquet `vendor/`
en local ni de zipper le projet.

---

## 0. Prérequis à vérifier dans cPanel

1. **Version PHP ≥ 8.3** : cPanel → *Sélectionner la version de PHP* (ou
   *MultiPHP Manager*). Sélectionne 8.3 (ou plus récent) pour le
   sous-domaine `keys.agoudatech.com`.
2. **Extension `sodium` activée** : dans le même écran, section
   *Extensions PHP*, coche `sodium` si elle ne l'est pas déjà (elle est
   indispensable — c'est ce qui signe les réponses de l'API). Coche aussi
   `pdo_mysql`, `mbstring`, `openssl`, `tokenizer`, `xml`, `ctype`,
   `fileinfo` si elles ne le sont pas déjà (généralement activées par
   défaut).
3. **Une base de données MySQL** (voir étape 2).
4. **Accès SSH activé** : cPanel → *SSH Access* → vérifie que ta clé/mot
   de passe fonctionne (`ssh tonuser@keys.agoudatech.com` ou l'hôte SSH
   indiqué par LWS — pas forcément le même nom que le sous-domaine,
   vérifie dans l'écran *SSH Access* de cPanel).

---

## 1. Créer le sous-domaine et poser le code

Dans cPanel → **Sous-domaines**, crée `keys` sur `agoudatech.com`. Note le
chemin proposé par défaut, généralement :

```
/home/<ton-user-cpanel>/keys.agoudatech.com/
```

Connecte-toi en SSH, puis récupère le code du projet. Deux options :

**Option A — via Git** (si le projet est poussé sur un dépôt distant) :

```bash
cd ~
git clone <url-du-repo> keys-api
```

**Option B — via SFTP/rsync** depuis ta machine locale (pas de dépôt
distant) :

```bash
rsync -avz --exclude vendor --exclude node_modules --exclude .env \
  "chemin/local/projet-licences/" tonuser@keys.agoudatech.com:~/keys-api/
```

Dans les deux cas, le dossier `~/keys-api` doit être **en dehors** de
`public_html` — seul son sous-dossier `public/` sera exposé au web (voir
étape 3).

---

## 2. Créer la base de données MySQL

Dans cPanel → **Bases de données MySQL** :

1. Crée une base, ex. `keys` (cPanel la préfixera automatiquement, ex.
   `agoudate_keys`).
2. Crée un utilisateur MySQL avec un mot de passe fort.
3. Ajoute cet utilisateur à la base avec **tous les privilèges**.
4. Note les 3 valeurs : nom de la base, nom d'utilisateur, mot de passe —
   elles vont dans `.env` à l'étape 4.

---

## 3. Configurer la racine web du sous-domaine

cPanel → **Sous-domaines** (ou **Domaines** selon la version) → trouve
`keys.agoudatech.com` → modifie son **Document Root** pour qu'il pointe
sur :

```
/home/<ton-user-cpanel>/keys-api/public
```

Apache servira directement le bon dossier, et tout le reste du projet
(`.env`, `app/`, `vendor/`...) restera inaccessible depuis le web. Si LWS
ne permet pas de modifier ce chemin pour un sous-domaine créé via
l'interface, recrée-le en indiquant directement
`/home/<ton-user-cpanel>/keys-api/public` comme *Document Root* dès la
création plutôt que le chemin par défaut.

---

## 4. Installer les dépendances et configurer `.env` (en SSH)

```bash
cd ~/keys-api
composer install --no-dev --optimize-autoloader
cp .env.example .env
php artisan key:generate
php artisan license:signing-keys:generate
```

Édite ensuite `.env` (`nano .env` ou `vi .env`) :

```
APP_NAME="API Licences"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://keys.agoudatech.com

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=agoudate_keys
DB_USERNAME=agoudate_keys_user
DB_PASSWORD=le-mot-de-passe-choisi

SESSION_SECURE_COOKIE=true

LICENSE_APP_TOKEN=<génère une valeur aléatoire forte, ex. `php artisan tinker --execute="echo Str::random(64);"`>
```

`LICENSE_SIGNING_PRIVATE_KEY` / `LICENSE_SIGNING_PUBLIC_KEY` ont déjà été
remplies par `license:signing-keys:generate` ci-dessus — ne les régénère
pas après la mise en prod (ça invaliderait toutes les licences déjà
signées).

**Garde une copie de `LICENSE_SIGNING_PUBLIC_KEY`** : c'est la clé à
embarquer dans le module client Flutter (voir
`guide-integration-licence-flutter.md`).

> Ce projet a précédemment été préparé pour `licences.agoudatech.com`
> (autre hébergeur). Ne réutilise pas ce `.env`-là tel quel : régénère des
> clés Ed25519 et un `LICENSE_APP_TOKEN` neufs pour ce nouveau
> déploiement, au cas où l'ancien `.env` aurait été exposé.

---

## 5. Migrations et compte admin (en SSH — plus besoin de Cron)

```bash
php artisan migrate --force
php artisan make:filament-user --name="Admin" --email="drickoma@gmail.com" --password="CHANGE-MOI-UN-MOT-DE-PASSE-FORT" --no-interaction
```

---

## 6. Permissions des dossiers

```bash
chmod -R 755 storage bootstrap/cache
```

(passe à `775` uniquement si `755` ne suffit pas selon la config du
serveur).

---

## 7. Mettre le framework en cache pour de meilleures perfs

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

⚠️ Si tu modifies `.env` **après** avoir fait `config:cache`, les
changements ne seront pas pris en compte tant que tu n'auras pas relancé
`php artisan config:clear`.

---

## 8. Activer le HTTPS

cPanel → **SSL/TLS Status** (ou **AutoSSL**) → sélectionne
`keys.agoudatech.com` → lance/valide l'émission d'un certificat Let's
Encrypt gratuit.

Le `.env` a `SESSION_SECURE_COOKIE=true`, donc les cookies de session du
panel admin exigent HTTPS — normal, ne reviens pas en arrière sur ce
point.

---

## 9. Vérification finale

1. Ouvre `https://keys.agoudatech.com/admin/login` → la page de connexion
   Filament doit s'afficher, avec un cadenas HTTPS valide.
2. Connecte-toi avec le compte admin créé à l'étape 5.
3. Crée un produit de test, génère une clé
   (`php artisan license:generate` en SSH, ou directement dans le panel).
4. Teste l'API :

```bash
curl -s -X POST https://keys.agoudatech.com/api/v1/activate \
  -H "Content-Type: application/json" \
  -H "X-App-Token: <LICENSE_APP_TOKEN du .env>" \
  -d '{"key":"...","product_slug":"...","machine_fingerprint":"sha256:'"$(printf 'a%.0s' {1..64})"'","nonce":"test123456"}'
```

Tu dois recevoir un JSON `{"payload": "...", "signature": "..."}`.

5. Vérifie que `https://keys.agoudatech.com/.env` renvoie bien une erreur
   (403/404), jamais le contenu du fichier.

---

## 10. Mises à jour futures

```bash
cd ~/keys-api
git pull   # ou re-rsync si pas de dépôt distant
composer install --no-dev --optimize-autoloader
php artisan migrate --force   # si nouvelles migrations
php artisan config:clear && php artisan config:cache
php artisan route:cache
php artisan view:cache
```

---

## Récapitulatif sécurité à ne pas oublier

- [ ] `.env` de production **différent** de celui de développement et de
      l'ancien déploiement `licences.agoudatech.com` (clés Ed25519 et
      token d'application propres à `keys.agoudatech.com`).
- [ ] `APP_DEBUG=false` en production.
- [ ] Projet placé **hors** de `public_html`, Document Root du
      sous-domaine pointé directement sur `public/`.
- [ ] `.env` inaccessible depuis le web (testé à l'étape 9).
- [ ] HTTPS actif, `SESSION_SECURE_COOKIE=true`.
- [ ] Mot de passe admin Filament fort, changé par rapport à celui de dev.
- [ ] Envisager de renseigner `ADMIN_ALLOWED_IPS` si tu as une IP fixe
      pour administrer le panel (§7.3.4 du cahier des charges).
- [ ] Penser à supprimer/désactiver l'ancien sous-domaine
      `licences.agoudatech.com` et sa base de données une fois la
      migration vers `keys.agoudatech.com` validée.
