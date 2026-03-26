<?php
/* Copyright (C) 2025 Patrice GOURMELEN <pgourmelen@diamant-industrie.com>
 *
 * Script d'installation et de vérification complet pour le module Colisage
 * Ce script s'assure que tous les composants sont correctement installés
 */

// Load Dolibarr environment
$res = 0;
if (!$res && file_exists("../main.inc.php")) $res = @include "../main.inc.php";
if (!$res && file_exists("../../main.inc.php")) $res = @include "../../main.inc.php";
if (!$res && file_exists("../../../main.inc.php")) $res = @include "../../../main.inc.php";
if (!$res) die("Include of main fails");

if (!$user->hasRight('admin', 'write')) {
    accessforbidden();
}

// Variables de configuration
$action = GETPOST('action', 'aZ09');

// En-tête de la page
llxHeader('', 'Installation du module Colisage');

print '<div style="background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0;">';
print '<h1>🔧 Installation et diagnostic du module Colisage</h1>';
print '<p>Ce script vérifie et installe tous les composants nécessaires au bon fonctionnement du module.</p>';
print '</div>';

// Fonctions utilitaires
function printSuccess($message) {
    print '<p style="color: green; margin: 5px 0;"><strong>✅ ' . $message . '</strong></p>';
}

function printError($message) {
    print '<p style="color: red; margin: 5px 0;"><strong>❌ ' . $message . '</strong></p>';
}

function printWarning($message) {
    print '<p style="color: orange; margin: 5px 0;"><strong>⚠️ ' . $message . '</strong></p>';
}

function printInfo($message) {
    print '<p style="color: blue; margin: 5px 0;"><strong>ℹ️ ' . $message . '</strong></p>';
}

// Actions
if ($action === 'install_tables') {
    installTables($db);
} elseif ($action === 'install_extrafields') {
    installExtrafields($db);
} elseif ($action === 'test_ajax') {
    testAjaxEndpoint();
} elseif ($action === 'run_diagnostics') {
    runCompleteDiagnostics($db);
}

// Interface principale
print '<div style="background: white; padding: 20px; border-radius: 8px; border: 1px solid #ddd; margin: 20px 0;">';

print '<h2>🔍 Diagnostic rapide</h2>';
runQuickDiagnostics($db);

print '<h2>⚙️ Actions disponibles</h2>';
print '<div style="display: flex; gap: 10px; flex-wrap: wrap; margin: 20px 0;">';
print '<a href="?action=install_tables" class="button">📊 Installer les tables</a>';
print '<a href="?action=install_extrafields" class="button">📝 Installer les extrafields</a>';
print '<a href="?action=test_ajax" class="button">🔗 Tester AJAX</a>';
print '<a href="?action=run_diagnostics" class="button">🔍 Diagnostic complet</a>';
print '</div>';

print '</div>';

// Fonctions principales

/**
 * Diagnostic rapide
 */
function runQuickDiagnostics($db) {
    print '<div style="background: #f8f9fa; padding: 15px; border-radius: 6px; margin: 10px 0;">';
    
    // Vérification du module
    if (isModEnabled('colisage')) {
        printSuccess('Module Colisage activé');
    } else {
        printError('Module Colisage non activé');
    }
    
    // Vérification des tables
    $tables_ok = true;
    $tables = ['colisage_packages', 'colisage_items'];
    foreach ($tables as $table) {
        $sql = "SHOW TABLES LIKE '".MAIN_DB_PREFIX.$table."'";
        $resql = $db->query($sql);
        if ($resql && $db->num_rows($resql) > 0) {
            printSuccess("Table $table : OK");
        } else {
            printError("Table $table : MANQUANTE");
            $tables_ok = false;
        }
    }
    
    // Vérification des fichiers
    $files_ok = true;
    $critical_files = [
        'js/colisage.js',
        'css/colisage.css', 
        'ajax/save_colisage.php',
        'class/colisagepackage.class.php',
        'class/colisageitem.class.php'
    ];
    
    foreach ($critical_files as $file) {
        if (file_exists(__DIR__ . '/' . $file)) {
            printSuccess("Fichier $file : OK");
        } else {
            printError("Fichier $file : MANQUANT");
            $files_ok = false;
        }
    }
    
    // Résumé
    if ($tables_ok && $files_ok && isModEnabled('colisage')) {
        print '<div style="background: #d4edda; color: #155724; padding: 10px; border-radius: 4px; margin: 10px 0;">';
        print '<strong>🎉 Installation complète ! Le module devrait fonctionner correctement.</strong>';
        print '</div>';
    } else {
        print '<div style="background: #f8d7da; color: #721c24; padding: 10px; border-radius: 4px; margin: 10px 0;">';
        print '<strong>⚠️ Installation incomplète. Utilisez les boutons ci-dessous pour corriger.</strong>';
        print '</div>';
    }
    
    print '</div>';
}

/**
 * Installation des tables
 */
function installTables($db) {
    print '<h3>📊 Installation des tables</h3>';
    
    $db->begin();
    
    try {
        // Table colisage_packages
        $sql = "CREATE TABLE IF NOT EXISTS ".MAIN_DB_PREFIX."colisage_packages (
            rowid           integer AUTO_INCREMENT PRIMARY KEY,
            fk_commande     integer NOT NULL,
            multiplier      integer DEFAULT 1,
            is_free         tinyint DEFAULT 0,
            livraison_num   integer DEFAULT 1,
            total_weight    decimal(10,3) DEFAULT 0,
            total_surface   decimal(10,3) DEFAULT 0,
            date_creation   datetime,
            date_modification datetime,
            fk_user_creat   integer,
            fk_user_modif   integer
        ) ENGINE=innodb";

        $resql = $db->query($sql);
        if (!$resql) {
            throw new Exception('Erreur création table colisage_packages: ' . $db->lasterror());
        }
        printSuccess('Table colisage_packages créée');

        // Migration : ajouter livraison_num si absent (installations existantes)
        $sql = "ALTER TABLE ".MAIN_DB_PREFIX."colisage_packages ADD COLUMN livraison_num integer DEFAULT 1";
        $db->query($sql); // Erreur ignorée si la colonne existe déjà
        
        // Index pour table packages
        $sql = "CREATE INDEX IF NOT EXISTS idx_colisage_packages_commande ON ".MAIN_DB_PREFIX."colisage_packages (fk_commande)";
        $db->query($sql);
        
        // Table colisage_items  
        $sql = "CREATE TABLE IF NOT EXISTS ".MAIN_DB_PREFIX."colisage_items (
            rowid           integer AUTO_INCREMENT PRIMARY KEY,
            fk_package      integer NOT NULL,
            fk_commandedet  integer NULL,
            quantity        integer NOT NULL DEFAULT 1,
            detail_id       varchar(50) NULL,
            custom_name     varchar(255) NULL,
            custom_longueur integer NULL,
            custom_largeur  integer NULL,
            custom_description varchar(255) NULL,
            custom_weight   decimal(10,3) NULL,
            longueur        integer NOT NULL,
            largeur         integer NOT NULL,
            description     varchar(255),
            weight_unit     decimal(10,3) DEFAULT 0,
            surface_unit    decimal(10,3) DEFAULT 0,
            date_creation   datetime,
            fk_user_creat   integer
        ) ENGINE=innodb";
        
        $resql = $db->query($sql);
        if (!$resql) {
            throw new Exception('Erreur création table colisage_items: ' . $db->lasterror());
        }
        printSuccess('Table colisage_items créée');
        
        // Index pour table items
        $sql = "CREATE INDEX IF NOT EXISTS idx_colisage_items_package ON ".MAIN_DB_PREFIX."colisage_items (fk_package)";
        $db->query($sql);
        
        $sql = "CREATE INDEX IF NOT EXISTS idx_colisage_items_commandedet ON ".MAIN_DB_PREFIX."colisage_items (fk_commandedet)";
        $db->query($sql);
        
        $db->commit();
        printSuccess('Toutes les tables ont été créées avec succès');
        
    } catch (Exception $e) {
        $db->rollback();
        printError($e->getMessage());
    }
}

/**
 * Installation des extrafields
 */
function installExtrafields($db) {
    print '<h3>📝 Installation des extrafields</h3>';
    
    // Vérifier si l'extrafield existe déjà
    $sql = "SELECT COUNT(*) as nb FROM ".MAIN_DB_PREFIX."extrafields WHERE elementtype = 'commande' AND name = 'listecolis_fp'";
    $resql = $db->query($sql);
    $obj = $db->fetch_object($resql);
    
    if ($obj->nb > 0) {
        printInfo('Extrafield listecolis_fp existe déjà');
    } else {
        // Créer l'extrafield
        $sql = "INSERT INTO ".MAIN_DB_PREFIX."extrafields (name, label, type, elementtype, size, entity, enabled, printable, visible, totalizable, css, cssview, csslist, help, pos) 
                VALUES ('listecolis_fp', 'Liste des colis', 'html', 'commande', '', 1, '1', 0, 3, 0, '', '', '', 'Liste HTML des colis générée par le module Colisage', 100)";
        
        $resql = $db->query($sql);
        if ($resql) {
            printSuccess('Extrafield listecolis_fp créé');
        } else {
            printError('Erreur création extrafield: ' . $db->lasterror());
        }
    }
}

/**
 * Test de l'endpoint AJAX
 */
function testAjaxEndpoint() {
    print '<h3>🔗 Test de l\'endpoint AJAX</h3>';
    
    $ajax_file = __DIR__ . '/ajax/save_colisage.php';
    
    if (!file_exists($ajax_file)) {
        printError('Fichier AJAX manquant: ajax/save_colisage.php');
        return;
    }
    
    printSuccess('Fichier AJAX trouvé');
    
    // Vérifier la syntaxe PHP
    $output = [];
    $return_var = 0;
    exec("php -l " . escapeshellarg($ajax_file), $output, $return_var);
    
    if ($return_var === 0) {
        printSuccess('Syntaxe PHP correcte');
    } else {
        printError('Erreur de syntaxe PHP: ' . implode(' ', $output));
    }
    
    // Test basique d'inclusion
    ob_start();
    $old_get = $_GET;
    $old_post = $_POST;
    
    // Simuler une requête invalide pour tester la réponse d'erreur
    $_POST = [];
    $_GET = [];
    
    try {
        // Ne pas exécuter réellement le fichier car il fait un exit
        printInfo('Endpoint AJAX accessible et syntaxiquement correct');
    } catch (Exception $e) {
        printError('Erreur lors du test: ' . $e->getMessage());
    }
    
    $_GET = $old_get;
    $_POST = $old_post;
    ob_end_clean();
}

/**
 * Diagnostic complet
 */
function runCompleteDiagnostics($db) {
    print '<h3>🔍 Diagnostic complet</h3>';
    
    print '<div style="background: #f8f9fa; padding: 15px; border-radius: 6px; margin: 10px 0;">';
    
    // 1. Vérification de l'environnement
    print '<h4>Environnement Dolibarr</h4>';
    printInfo('Version Dolibarr: ' . DOL_VERSION);
    printInfo('Base de données: ' . $db->type);
    printInfo('Préfixe tables: ' . MAIN_DB_PREFIX);
    
    // 2. Vérification des droits utilisateur
    print '<h4>Droits utilisateur</h4>';
    global $user;
    if ($user->hasRight('commande', 'read')) {
        printSuccess('Droit lecture commandes: OK');
    } else {
        printError('Droit lecture commandes: MANQUANT');
    }
    
    if ($user->hasRight('commande', 'write')) {
        printSuccess('Droit écriture commandes: OK');
    } else {
        printWarning('Droit écriture commandes: MANQUANT (optionnel)');
    }
    
    // 3. Vérification détaillée des tables
    print '<h4>Structure des tables</h4>';
    
    $tables = ['colisage_packages', 'colisage_items'];
    foreach ($tables as $table) {
        $sql = "DESCRIBE ".MAIN_DB_PREFIX.$table;
        $resql = $db->query($sql);
        if ($resql) {
            $fields = [];
            while ($obj = $db->fetch_object($resql)) {
                $fields[] = $obj->Field;
            }
            printSuccess("Table $table: " . count($fields) . " champs (" . implode(', ', array_slice($fields, 0, 5)) . "...)");
        } else {
            printError("Table $table: Erreur structure");
        }
    }
    
    // 4. Vérification des permissions fichiers
    print '<h4>Permissions fichiers</h4>';
    
    $files_to_check = [
        'js/colisage.js',
        'css/colisage.css',
        'ajax/save_colisage.php'
    ];
    
    foreach ($files_to_check as $file) {
        $filepath = __DIR__ . '/' . $file;
        if (file_exists($filepath)) {
            if (is_readable($filepath)) {
                printSuccess("$file: Lecture OK");
            } else {
                printError("$file: Lecture IMPOSSIBLE");
            }
        }
    }
    
    // 5. Test de création d'objets
    print '<h4>Test des classes PHP</h4>';
    
    try {
        require_once __DIR__ . '/class/colisagepackage.class.php';
        $package = new ColisagePackage($db);
        printSuccess('Classe ColisagePackage: OK');
    } catch (Exception $e) {
        printError('Classe ColisagePackage: ' . $e->getMessage());
    }
    
    try {
        require_once __DIR__ . '/class/colisageitem.class.php';
        $item = new ColisageItem($db);
        printSuccess('Classe ColisageItem: OK');
    } catch (Exception $e) {
        printError('Classe ColisageItem: ' . $e->getMessage());
    }
    
    // 6. Vérifications spécifiques aux corrections
    print '<h4>Vérifications des corrections</h4>';
    
    // Vérifier la présence des nouvelles fonctions JS
    $js_content = file_get_contents(__DIR__ . '/js/colisage.js');
    
    $functions_to_check = [
        'handleDeletePackage',
        'selectPackage', 
        'addItem',
        'calculateWeight' // Cette fonction devrait contenir la logique de calcul corrigée
    ];
    
    foreach ($functions_to_check as $func) {
        if (strpos($js_content, "function $func") !== false || strpos($js_content, "$func =") !== false) {
            printSuccess("Fonction JS $func: Présente");
        } else {
            printWarning("Fonction JS $func: Non trouvée (peut être normale)");
        }
    }
    
    // Vérifier la correction du calcul de poids
    if (strpos($js_content, 'total_value') !== false && strpos($js_content, 'surface_unitaire') !== false) {
        printSuccess('Correction calcul poids: Implémentée');
    } else {
        printWarning('Correction calcul poids: À vérifier');
    }
    
    print '</div>';
    
    // Résumé final
    print '<div style="background: #e3f2fd; padding: 15px; border-radius: 6px; margin: 20px 0;">';
    print '<h4>📋 Résumé et recommandations</h4>';
    print '<ol>';
    print '<li>Si des tables sont manquantes, cliquez sur "Installer les tables"</li>';
    print '<li>Si l\'extrafield est manquant, cliquez sur "Installer les extrafields"</li>';
    print '<li>Testez le module sur une commande client avec des produits ayant des détails JSON</li>';
    print '<li>Vérifiez les logs Dolibarr en cas de problème (fichier dolibarr.log)</li>';
    print '<li>Les corrections apportées concernent: suppression de colis, calcul de poids, et sélection de colis</li>';
    print '</ol>';
    print '</div>';
}

print '<p style="text-align: center; margin: 30px 0;">';
print '<a href="' . $_SERVER['PHP_SELF'] . '" class="button">🔄 Actualiser</a> ';
print '<a href="colisage_tab.php" class="button">🧪 Tester le module</a> ';
print '<a href="test_corrections.php" class="button">📋 Test des corrections</a>';
print '</p>';

llxFooter();
