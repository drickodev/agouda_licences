<?php

/**
 * Front controller à placer dans htdocs/<sous-domaine>/index.php sur
 * l'hébergement LWS. Le code Laravel complet vit en dehors de la racine
 * web, dans htdocs/<nom>_core/ (ex. keys_core, licences_core) — seul ce
 * fichier + les assets publics (css/, js/, favicon.ico, robots.txt,
 * .htaccess) sont copiés dans le dossier du sous-domaine.
 *
 * Particularité LWS : le chemin vu par PHP (chroot) est /htdocs/... même
 * si SSH/FTP montre parfois une racine différente. Adapte uniquement
 * $projectRoot ci-dessous.
 */

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

$projectRoot = '/htdocs/keys_core';

if (file_exists($maintenance = $projectRoot.'/storage/framework/maintenance.php')) {
    require $maintenance;
}

require $projectRoot.'/vendor/autoload.php';

/** @var Application $app */
$app = require_once $projectRoot.'/bootstrap/app.php';

$app->handleRequest(Request::capture());
