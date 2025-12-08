<?php
/* Copyright (C) 2025 Patrice GOURMELEN <pgourmelen@diamant-industrie.com>
 *
 * Script de diagnostic pour comprendre comment ref_chantier est stocké
 */

// Load Dolibarr environment
$res = 0;
if (!$res && file_exists("../main.inc.php")) $res = @include "../main.inc.php";
if (!$res && file_exists("../../main.inc.php")) $res = @include "../../main.inc.php";
if (!$res && file_exists("../../../main.inc.php")) $res = @include "../../../main.inc.php";
if (!$res) die("Include of main fails");

require_once DOL_DOCUMENT_ROOT.'/commande/class/commande.class.php';

// Vérification des droits
if (!$user->admin) {
    accessforbidden('Admin rights required');
}

// Récupérer l'ID de commande
$id = GETPOSTINT('id');

if (empty($id)) {
    die('Veuillez spécifier un ID de commande : ?id=XXX');
}

// Charger la commande
$commande = new Commande($db);
$result = $commande->fetch($id);

if ($result < 0) {
    die('Erreur lors du chargement de la commande : ' . $commande->error);
}

$commande->fetch_lines();

print '<!DOCTYPE html>';
print '<html><head><title>Diagnostic ref_chantier - Commande ' . $commande->ref . '</title>';
print '<style>
body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
h1 { color: #333; }
h2 { color: #0066cc; margin-top: 30px; }
table { border-collapse: collapse; width: 100%; background: white; margin: 10px 0; }
th { background: #0066cc; color: white; padding: 10px; text-align: left; }
td { padding: 8px; border: 1px solid #ddd; }
tr:nth-child(even) { background: #f9f9f9; }
.service { background: #fff3cd !important; }
.product { background: #d4edda !important; }
.warning { background: #f8d7da !important; }
.info { background: #d1ecf1; padding: 10px; border-left: 4px solid #0066cc; margin: 10px 0; }
.success { background: #d4edda; padding: 10px; border-left: 4px solid #28a745; margin: 10px 0; }
.error { background: #f8d7da; padding: 10px; border-left: 4px solid #dc3545; margin: 10px 0; }
</style>';
print '</head><body>';

print '<h1>🔍 Diagnostic ref_chantier</h1>';
print '<div class="info">Commande : <strong>' . $commande->ref . '</strong> (ID: ' . $id . ')</div>';

// PARTIE 1 : Analyser toutes les lignes de commande
print '<h2>📋 Analyse des lignes de commande</h2>';

print '<table>';
print '<thead><tr>';
print '<th>Rowid</th>';
print '<th>Type</th>';
print '<th>Rang</th>';
print '<th>Label</th>';
print '<th>Qty</th>';
print '<th>ref_chantier (extrafield)</th>';
print '<th>A un extrafield ?</th>';
print '</tr></thead>';
print '<tbody>';

$services = array();
$produits = array();

foreach ($commande->lines as $line) {
    $type_label = '';
    $css_class = '';
    
    if ($line->product_type == 9) {
        $type_label = 'SERVICE/TITRE';
        $css_class = 'service';
        $services[] = $line;
    } else if ($line->product_type == 0 || empty($line->product_type)) {
        $type_label = 'PRODUIT';
        $css_class = 'product';
        $produits[] = $line;
    } else {
        $type_label = 'Type ' . $line->product_type;
    }
    
    $ref_chantier = isset($line->array_options['options_ref_chantier']) ? $line->array_options['options_ref_chantier'] : '';
    
    // Vérifier si un extrafield existe pour cette ligne
    $sql = "SELECT ref_chantier FROM " . MAIN_DB_PREFIX . "commandedet_extrafields WHERE fk_object = " . ((int) $line->rowid);
    $resql = $db->query($sql);
    $has_extrafield = false;
    $extrafield_value = '';
    
    if ($resql && $db->num_rows($resql) > 0) {
        $has_extrafield = true;
        $obj = $db->fetch_object($resql);
        $extrafield_value = $obj->ref_chantier;
    }
    
    print '<tr class="' . $css_class . '">';
    print '<td>' . $line->rowid . '</td>';
    print '<td><strong>' . $type_label . '</strong></td>';
    print '<td>' . $line->rang . '</td>';
    print '<td>' . htmlspecialchars($line->label) . '</td>';
    print '<td>' . $line->qty . '</td>';
    print '<td>' . htmlspecialchars($extrafield_value) . '</td>';
    print '<td>' . ($has_extrafield ? '✅ Oui' : '❌ Non') . '</td>';
    print '</tr>';
}

print '</tbody></table>';

// PARTIE 2 : Analyser la table des extrafields directement
print '<h2>🗄️ Contenu de la table commandedet_extrafields</h2>';

$sql = "SELECT e.rowid, e.fk_object, e.ref_chantier, c.product_type, c.label, c.rang
        FROM " . MAIN_DB_PREFIX . "commandedet_extrafields e
        LEFT JOIN " . MAIN_DB_PREFIX . "commandedet c ON c.rowid = e.fk_object
        WHERE c.fk_commande = " . ((int) $id) . "
        ORDER BY c.rang";

$resql = $db->query($sql);

if ($resql) {
    $num_extrafields = $db->num_rows($resql);
    
    print '<div class="info">Nombre d\'extrafields trouvés : <strong>' . $num_extrafields . '</strong></div>';
    
    print '<table>';
    print '<thead><tr>';
    print '<th>Extrafield ID</th>';
    print '<th>fk_object (rowid ligne)</th>';
    print '<th>Type ligne</th>';
    print '<th>Rang</th>';
    print '<th>Label ligne</th>';
    print '<th>ref_chantier</th>';
    print '</tr></thead>';
    print '<tbody>';
    
    $ref_chantier_values = array();
    
    while ($obj = $db->fetch_object($resql)) {
        $type_label = $obj->product_type == 9 ? 'SERVICE' : 'PRODUIT';
        $css_class = $obj->product_type == 9 ? 'service' : 'product';
        
        if (!empty($obj->ref_chantier)) {
            if (!isset($ref_chantier_values[$obj->ref_chantier])) {
                $ref_chantier_values[$obj->ref_chantier] = array();
            }
            $ref_chantier_values[$obj->ref_chantier][] = array(
                'rowid' => $obj->fk_object,
                'type' => $type_label,
                'label' => $obj->label
            );
        }
        
        print '<tr class="' . $css_class . '">';
        print '<td>' . $obj->rowid . '</td>';
        print '<td>' . $obj->fk_object . '</td>';
        print '<td><strong>' . $type_label . '</strong></td>';
        print '<td>' . $obj->rang . '</td>';
        print '<td>' . htmlspecialchars($obj->label) . '</td>';
        print '<td>' . htmlspecialchars($obj->ref_chantier) . '</td>';
        print '</tr>';
    }
    
    print '</tbody></table>';
    
    // PARTIE 3 : Analyser les valeurs dupliquées
    print '<h2>⚠️ Analyse des valeurs ref_chantier dupliquées</h2>';
    
    $has_duplicates = false;
    
    foreach ($ref_chantier_values as $value => $lines) {
        if (count($lines) > 1) {
            $has_duplicates = true;
            
            print '<div class="warning">';
            print '<strong>Valeur dupliquée :</strong> "' . htmlspecialchars($value) . '" (' . count($lines) . ' lignes)<br>';
            print '<ul>';
            foreach ($lines as $line) {
                print '<li>Ligne ' . $line['rowid'] . ' (' . $line['type'] . ') : ' . htmlspecialchars($line['label']) . '</li>';
            }
            print '</ul>';
            print '</div>';
        }
    }
    
    if (!$has_duplicates) {
        print '<div class="success">✅ Aucune valeur ref_chantier dupliquée trouvée</div>';
    }
    
} else {
    print '<div class="error">Erreur lors de la requête : ' . $db->lasterror() . '</div>';
}

// PARTIE 4 : Statistiques
print '<h2>📊 Statistiques</h2>';

print '<div class="info">';
print '<strong>Total des lignes de commande :</strong> ' . count($commande->lines) . '<br>';
print '<strong>Services/Titres (type 9) :</strong> ' . count($services) . '<br>';
print '<strong>Produits (type 0) :</strong> ' . count($produits) . '<br>';
print '</div>';

// PARTIE 5 : Requête de test
print '<h2>🧪 Test de mise à jour</h2>';

if (count($services) > 0) {
    $test_service = $services[0];
    
    print '<div class="info">';
    print '<strong>Service de test :</strong> ' . htmlspecialchars($test_service->label) . ' (rowid: ' . $test_service->rowid . ')<br>';
    print '<br>';
    print '<strong>Requête SQL qui serait exécutée pour mettre à jour ce titre :</strong><br>';
    print '<code style="background: #f0f0f0; padding: 5px; display: block; margin: 10px 0;">';
    print 'UPDATE ' . MAIN_DB_PREFIX . 'commandedet_extrafields<br>';
    print 'SET ref_chantier = \'NOUVEAU_TITRE\'<br>';
    print 'WHERE fk_object = ' . $test_service->rowid;
    print '</code>';
    print '<br>';
    print '<strong>Cette requête devrait mettre à jour UNIQUEMENT la ligne ' . $test_service->rowid . '</strong>';
    print '</div>';
}

// PARTIE 6 : Recommandations
print '<h2>💡 Recommandations</h2>';

if ($has_duplicates) {
    print '<div class="error">';
    print '<strong>⚠️ PROBLÈME DÉTECTÉ :</strong><br>';
    print 'Plusieurs lignes partagent la même valeur ref_chantier. Ceci peut causer des comportements inattendus.<br><br>';
    print '<strong>Solutions possibles :</strong><br>';
    print '1. Nettoyer les valeurs ref_chantier des lignes de produits (recommandé)<br>';
    print '2. Utiliser un extrafield différent spécifique aux titres de sections<br>';
    print '</div>';
} else {
    print '<div class="success">';
    print '✅ La structure des données semble correcte.<br>';
    print 'Si vous rencontrez toujours le problème de mise à jour multiple, il peut y avoir :<br>';
    print '• Un trigger Dolibarr qui copie ref_chantier<br>';
    print '• Un hook du module détailproduit ou autre<br>';
    print '• Un problème dans le code JavaScript qui envoie plusieurs requêtes<br>';
    print '</div>';
}

print '<p><a href="colisage_tab.php?id=' . $id . '">← Retour au colisage</a></p>';

print '</body></html>';
