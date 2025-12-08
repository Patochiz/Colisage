<?php
/* Diagnostic des cartes de titres - Service ID=361 */

// Load Dolibarr environment
$res = 0;
if (!$res && file_exists("../../main.inc.php")) $res = @include "../../main.inc.php";
if (!$res && file_exists("../../../main.inc.php")) $res = @include "../../../main.inc.php";
if (!$res && file_exists("../../../../main.inc.php")) $res = @include "../../../../main.inc.php";
if (!$res && file_exists("../../../../../main.inc.php")) $res = @include "../../../../../main.inc.php";
if (!$res) die("Include of main fails");

require_once DOL_DOCUMENT_ROOT.'/commande/class/commande.class.php';
require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';

// Get order ID
$id = GETPOSTINT('id');

if (empty($id)) {
    print "❌ Aucun ID de commande fourni. Utilisez: ?id=XXX<br>";
    exit;
}

// Load order
$object = new Commande($db);
$result = $object->fetch($id);

if ($result < 0) {
    print "❌ Erreur lors du chargement de la commande: " . $object->error . "<br>";
    exit;
}

print "<h1>🔍 Diagnostic Cartes de Titres - Service ID=361</h1>";
print "<p><strong>Commande:</strong> " . $object->ref . " (ID: " . $id . ")</p>";
print "<hr>";

// Fetch lines
$object->fetch_lines();

print "<h2>📋 Analyse des lignes de commande</h2>";
print "<p><strong>Nombre total de lignes:</strong> " . count($object->lines) . "</p>";

$service_361_count = 0;
$sections_detectees = array();
$produits_count = 0;

print "<table border='1' cellpadding='5' cellspacing='0' style='border-collapse: collapse; width: 100%;'>";
print "<tr style='background: #f0f0f0;'>";
print "<th>Rang</th>";
print "<th>RowID</th>";
print "<th>Type</th>";
print "<th>fk_product</th>";
print "<th>Label</th>";
print "<th>ref_chantier</th>";
print "<th>Détection</th>";
print "</tr>";

foreach ($object->lines as $line) {
    $ref_chantier = '';
    if (!empty($line->array_options['options_ref_chantier'])) {
        $ref_chantier = $line->array_options['options_ref_chantier'];
    }

    $detection = '';
    $bg_color = '';

    // Check si c'est un titre (Service ID=361)
    if ($line->fk_product == 361 && $line->product_type == 1) {
        $service_361_count++;
        $detection = '✅ TITRE (Service ID=361)';
        $bg_color = 'background: #d4edda;';

        $sections_detectees[] = array(
            'rang' => $line->rang,
            'rowid' => $line->rowid,
            'label' => $line->label,
            'ref_chantier' => $ref_chantier
        );
    } elseif ($line->product_type == 0) {
        $detection = '📦 Produit physique';
        $bg_color = 'background: #e7f3ff;';
        $produits_count++;
    } elseif ($line->product_type == 1) {
        $detection = '🔧 Service (autre)';
        $bg_color = 'background: #fff3cd;';
    } elseif ($line->product_type == 9) {
        $detection = '⚠️ Ancien titre (type 9)';
        $bg_color = 'background: #f8d7da;';
    } else {
        $detection = '❓ Autre type';
    }

    print "<tr style='$bg_color'>";
    print "<td>" . $line->rang . "</td>";
    print "<td>" . $line->rowid . "</td>";
    print "<td>" . $line->product_type . "</td>";
    print "<td>" . $line->fk_product . "</td>";
    print "<td>" . htmlspecialchars($line->label) . "</td>";
    print "<td>" . htmlspecialchars($ref_chantier) . "</td>";
    print "<td><strong>" . $detection . "</strong></td>";
    print "</tr>";
}

print "</table>";

print "<hr>";
print "<h2>📊 Résumé</h2>";
print "<ul>";
print "<li><strong>Services ID=361 détectés:</strong> " . $service_361_count . "</li>";
print "<li><strong>Produits physiques (type=0):</strong> " . $produits_count . "</li>";
print "</ul>";

if ($service_361_count == 0) {
    print "<div style='background: #f8d7da; border: 2px solid #f5c6cb; padding: 15px; margin: 20px 0; border-radius: 5px;'>";
    print "<h3>❌ PROBLÈME IDENTIFIÉ</h3>";
    print "<p>Aucun service avec ID=361 n'a été détecté dans cette commande.</p>";
    print "<p><strong>Solutions possibles:</strong></p>";
    print "<ol>";
    print "<li>Vérifiez que le service ID=361 existe dans votre base de données (table llx_product)</li>";
    print "<li>Ajoutez le service ID=361 dans votre commande pour créer des titres de sections</li>";
    print "<li>Si vous utilisez l'ancien système, le code doit être modifié pour supporter product_type=9</li>";
    print "</ol>";
    print "</div>";
} else {
    print "<div style='background: #d4edda; border: 2px solid #c3e6cb; padding: 15px; margin: 20px 0; border-radius: 5px;'>";
    print "<h3>✅ Services ID=361 détectés</h3>";
    print "<p>" . $service_361_count . " titre(s) de section ont été détectés dans la commande.</p>";
    print "</div>";

    print "<h3>📋 Détail des sections détectées:</h3>";
    print "<table border='1' cellpadding='5' cellspacing='0' style='border-collapse: collapse; width: 100%;'>";
    print "<tr style='background: #f0f0f0;'>";
    print "<th>Rang</th>";
    print "<th>RowID</th>";
    print "<th>Label (titre par défaut)</th>";
    print "<th>ref_chantier (titre personnalisé)</th>";
    print "<th>Titre affiché</th>";
    print "</tr>";

    foreach ($sections_detectees as $section) {
        $titre_affiche = !empty($section['ref_chantier']) ? $section['ref_chantier'] : $section['label'];

        print "<tr>";
        print "<td>" . $section['rang'] . "</td>";
        print "<td>" . $section['rowid'] . "</td>";
        print "<td>" . htmlspecialchars($section['label']) . "</td>";
        print "<td>" . htmlspecialchars($section['ref_chantier']) . "</td>";
        print "<td><strong>" . htmlspecialchars($titre_affiche) . "</strong></td>";
        print "</tr>";
    }

    print "</table>";
}

print "<hr>";
print "<h2>🔍 Vérification dans la base de données</h2>";

// Vérifier si le service ID=361 existe
$sql = "SELECT rowid, ref, label, product_type FROM " . MAIN_DB_PREFIX . "product WHERE rowid = 361";
$resql = $db->query($sql);

if ($resql) {
    $obj = $db->fetch_object($resql);
    if ($obj) {
        print "<div style='background: #d4edda; padding: 10px; border-radius: 5px;'>";
        print "<p>✅ <strong>Service ID=361 trouvé dans la base de données</strong></p>";
        print "<ul>";
        print "<li><strong>Référence:</strong> " . $obj->ref . "</li>";
        print "<li><strong>Label:</strong> " . $obj->label . "</li>";
        print "<li><strong>Type:</strong> " . $obj->product_type . " " . ($obj->product_type == 1 ? '(Service)' : '(ERREUR: devrait être 1)') . "</li>";
        print "</ul>";
        print "</div>";
    } else {
        print "<div style='background: #f8d7da; padding: 10px; border-radius: 5px;'>";
        print "<p>❌ <strong>Service ID=361 NON TROUVÉ dans la base de données</strong></p>";
        print "<p>Vous devez créer un service avec l'ID 361 pour utiliser cette fonctionnalité.</p>";
        print "</div>";
    }
}

print "<hr>";
print "<p><a href='colisage_tab.php?id=" . $id . "'>&larr; Retour au module Colisage</a></p>";
print "<p><a href='colisage_tab.php?id=" . $id . "&debug=1'>&larr; Retour au module Colisage (mode debug)</a></p>";
?>
