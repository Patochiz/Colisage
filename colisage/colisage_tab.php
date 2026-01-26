<?php
/* Copyright (C) 2025 Patrice GOURMELEN <pgourmelen@diamant-industrie.com>
 *
 * Interface Colisage complète - Version avec sélection et quantité rapides + titres éditables
 * MODIFICATION : Utilise le service ID=361 pour les titres au lieu de product_type=9
 */

// Load Dolibarr environment SEULEMENT si pas déjà chargé
if (!defined('DOL_VERSION')) {
    $res = 0;
    if (!$res && file_exists("../../main.inc.php")) $res = @include "../../main.inc.php";
    if (!$res && file_exists("../../../main.inc.php")) $res = @include "../../../main.inc.php";
    if (!$res && file_exists("../../../../main.inc.php")) $res = @include "../../../../main.inc.php";
    if (!$res && file_exists("../../../../../main.inc.php")) $res = @include "../../../../../main.inc.php";
    if (!$res) die("Include of main fails");
}

require_once DOL_DOCUMENT_ROOT.'/commande/class/commande.class.php';
require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';

// Vérifier si les classes Colisage existent
$classPackagePath = __DIR__.'/class/colisagepackage.class.php';
$classItemPath = __DIR__.'/class/colisageitem.class.php';

if (file_exists($classPackagePath)) {
    require_once $classPackagePath;
}
if (file_exists($classItemPath)) {
    require_once $classItemPath;
}

// Load translation files
$langs->loadLangs(array("orders", "companies", "colisage@colisage"));

// Get parameters
$id = GETPOSTINT('id');
$action = GETPOST('action', 'aZ09');

// Security check
if (empty($id)) {
    accessforbidden();
}

$object = new Commande($db);
$result = $object->fetch($id);
if ($result < 0) {
    dol_print_error($db, $object->error);
    exit;
}

// Security check
if (!$user->hasRight('commande', 'read')) {
    accessforbidden();
}

// Vérification si le module est activé
if (!isModEnabled('colisage')) {
    print '<div class="error">Module Colisage non activé. Veuillez l\'activer dans la configuration des modules.</div>';
    exit;
}

// Debug mode - afficher les informations de debug si nécessaire
$debug_mode = GETPOST('debug', 'int');

/*
 * View - Interface avec sélection et quantité rapides
 */

llxHeader('', 'Colisage - Commande ' . $object->ref);

// Inclure le CSS du module (version corrigée)
$cssFile = dol_buildpath('/colisage/css/colisage.css', 1);
print '<link rel="stylesheet" type="text/css" href="'.$cssFile.'?v='.time().'">';

// Navigation simple avec liens de debug
print '<div style="background: #f8f9fa; padding: 10px; border-bottom: 1px solid #ddd; margin-bottom: 20px;">';
print '<a href="'.DOL_URL_ROOT.'/commande/card.php?id='.$id.'" style="text-decoration: none; color: #007bff;">← Retour à la commande</a>';
print ' | <strong>Module Colisage</strong> <span style="color: #28a745; font-size: 0.9rem;">(Version Optimisée)</span>';
if ($user->admin) {
    print ' | <a href="install_colisage.php" style="color: #28a745;">⚙️ Installation</a>';
    if (!$debug_mode) {
        print ' | <a href="?id='.$id.'&debug=1" style="color: #ffc107;">🐛 Debug</a>';
    } else {
        print ' | <a href="?id='.$id.'" style="color: #6c757d;">🐛 Masquer debug</a>';
    }
}
print '</div>';

// Messages de debug si activé
if ($debug_mode && $user->admin) {
    print '<div style="background: #fff3cd; border: 1px solid #ffeaa7; padding: 10px; margin-bottom: 15px; border-radius: 4px;">';
    print '<h4>🐛 Mode Debug activé</h4>';
    print '<p><strong>ID Commande:</strong> ' . $id . '</p>';
    print '<p><strong>Référence:</strong> ' . $object->ref . '</p>';
    print '<p><strong>Nombre de lignes:</strong> ' . count($object->lines) . '</p>';
    
    // Vérifier la présence du module détailproduit
    $has_detailproduit = false;
    foreach ($object->lines as $line) {
        if (!empty($line->array_options['options_detailjson'])) {
            $has_detailproduit = true;
            break;
        }
    }
    
    if ($has_detailproduit) {
        print '<p style="color: green;"><strong>Module détailproduit:</strong> ✅ Détails JSON détectés</p>';
    } else {
        print '<p style="color: orange;"><strong>Module détailproduit:</strong> ⚠️ Aucun détail JSON trouvé</p>';
    }
    
    print '</div>';
}

// Message d'aide sur les nouvelles fonctionnalités
print '<div style="background: #e7f3ff; border-left: 4px solid #2196f3; padding: 12px; margin-bottom: 20px; border-radius: 4px;">';
print '<div style="display: flex; align-items: center; gap: 10px;">';
print '<span style="font-size: 1.5rem;">💡</span>';
print '<div>';
print '<strong style="color: #1976d2;">Nouvelles fonctionnalités :</strong><br>';
print '<span style="font-size: 0.9rem; color: #424242;">';
print '• <strong>Auto-sauvegarde</strong> : Plus besoin de cliquer sur "Enregistrer" après chaque modification<br>';
print '• <strong>Sélection rapide</strong> : Cliquez sur le bouton <span style="background: #28a745; color: white; padding: 2px 6px; border-radius: 3px; font-weight: bold;">➕</span> dans le récapitulatif pour sélectionner directement un produit<br>';
print '• <strong>Quantités rapides</strong> : Utilisez les boutons ×4, ×6, ×8 ou Max pour gagner du temps<br>';
print '• <strong>Titres éditables</strong> : Cliquez sur l\'icône ✏️ pour personnaliser les titres de sections';
print '</span>';
print '</div>';
print '</div>';
print '</div>';

// Contenu principal
print '<div class="colisage-container">';

// En-tête avec informations
print '<div class="colisage-header">';
print '<h1>'.$langs->trans('Colisage').'</h1>';
print '<p>Commande Client : <span class="command-ref">'.$object->ref.'</span></p>';
if ($debug_mode) {
    print '<p style="font-size: 0.9rem; color: #6c757d;">Version avec sélection et quantité rapides + auto-sauvegarde + titres éditables (Service ID=361)</p>';
}
print '</div>';

// Actions debug EN HAUT (seulement si debug activé)
if ($user->admin && $debug_mode) {
    print '<div class="colisage-top-actions">';
    print '<div class="actions-left">';
    print '<button class="colisage-btn colisage-btn-secondary" onclick="console.log(\'État actuel:\', colisageApp)">🐛 Console</button>';
    print '<button class="colisage-btn colisage-btn-secondary" onclick="window.debugColisageSelection ? window.debugColisageSelection() : console.log(\'Debug non disponible\')">🧪 Test Sélection</button>';
    print '</div>';
    print '<div class="actions-right">';
    print '</div>';
    print '</div>';
}

// DISPOSITION EN 2 COLONNES
print '<div class="colisage-main-layout">';

// COLONNE GAUCHE (40%) : Récapitulatif des produits
print '<div class="colisage-left-column">';
print '<div class="summary-section">';
print '<div class="summary-header">Récapitulatif des Produits</div>';
print '<div class="summary-content" id="products-summary">';
print '<div class="colisage-loading">Chargement des produits...</div>';
print '</div>';
print '</div>';
print '</div>';

// COLONNE DROITE (60%) : Éditeur de colis + Liste des colis
print '<div class="colisage-right-column">';

// Éditeur de colis
print '<div class="colis-editor" id="colis-editor">';
print '<div class="empty-editor">';
print '<h3>Aucun colis sélectionné</h3>';
print '<p>Sélectionnez un colis dans la liste ci-dessous pour le modifier</p>';
print '</div>';
print '</div>';

// Actions principales sous l'éditeur
print '<div class="colisage-actions-bottom">';
print '<div class="actions-left">';
print '<button class="colisage-btn colisage-btn-secondary" id="btn-new-colis" onclick="createNewPackage()" disabled title="Plus de produits disponibles">+ Colis standard</button>';
print '<button class="colisage-btn colisage-btn-secondary" id="btn-free-colis" onclick="createFreePackage()">+ Colis libre</button>';
print '</div>';
print '<div class="actions-right">';
print '<button class="colisage-btn colisage-btn-secondary" onclick="window.history.back()">Annuler</button>';
print '<button class="colisage-btn colisage-btn-primary" id="save-btn" title="Sauvegarde manuelle (l\'auto-sauvegarde est activée)">💾 Sauvegarder</button>';
print '<div id="save-indicator" style="display: none; margin-left: 10px; align-self: center;">';
print '<span class="loading-spinner"></span> Sauvegarde...';
print '</div>';
print '</div>';
print '</div>';

// Liste des colis créés
print '<div class="summary-section" style="margin-top: 1rem;">';
print '<div class="summary-header">Colis Créés</div>';
print '<div class="summary-content" id="colis-summary">';
print '<div class="colisage-loading">Chargement des colis...</div>';
print '</div>';
print '</div>';

print '</div>'; // End colisage-right-column

print '</div>'; // End colisage-main-layout

print '</div>'; // End colisage-container

// Inclure les données JSON pour JavaScript (version améliorée)
$token = newToken();
?>
<script type="text/javascript">
// Configuration globale pour le module Colisage (version optimisée)
window.colisageData = {
    commandeId: <?php echo json_encode($id); ?>,
    commandeRef: <?php echo json_encode($object->ref); ?>,
    token: <?php echo json_encode($token); ?>,
    urlBase: <?php echo json_encode(dol_buildpath('/colisage', 1)); ?>,
    productData: {},
    packages: [],
    nextPackageId: 1,
    selectedPackageId: null,
    debugMode: <?php echo $debug_mode ? 'true' : 'false'; ?>
};

// Charger les données des produits de la commande (version améliorée)
<?php
$productData = array();
$sectionsData = array(); // NOUVEAU : Structure hiérarchique avec titres
$currentSection = null;
$produitsAvantPremierTitre = array(); // Produits avant le premier titre
$total_products = 0;
$total_details = 0;

// Récupérer les lignes de la commande
$object->fetch_lines();

foreach ($object->lines as $line) {
    // MODIFICATION : Détecter les titres de section via le service ID=361 (product_type=1)
    if ($line->fk_product == 361 && $line->product_type == 1) {
        // C'est un titre de section - créer une nouvelle section
        if ($currentSection !== null) {
            // Sauvegarder la section précédente
            $sectionsData[] = $currentSection;
        }
        
        // Récupérer l'extrafield ref_chantier
        $ref_chantier = '';
        if (!empty($line->array_options['options_ref_chantier'])) {
            $ref_chantier = $line->array_options['options_ref_chantier'];
        }

        // Logique simplifiée : ref_chantier en priorité, sinon description de la ligne
        if (!empty($ref_chantier)) {
            $titre_affiche = $ref_chantier;
        } else {
            // Utiliser la description de la ligne (desc ou description)
            $titre_affiche = !empty($line->desc) ? $line->desc : (!empty($line->description) ? $line->description : '');
        }
        
        // Créer une nouvelle section
        $currentSection = array(
            'titre' => $titre_affiche,
            'titre_original' => $line->label, // Titre original de la ligne
            'ref_chantier' => $ref_chantier,  // Extrafield ref_chantier
            'rowid' => $line->rowid,          // ID de la ligne pour pouvoir la mettre à jour
            'rang' => $line->rang,
            'produits' => array()
        );
        
        if ($debug_mode) {
            error_log("DEBUG COLISAGE - Titre de section détecté (Service ID=361): titre='{$titre_affiche}' (ref_chantier: '{$ref_chantier}', desc: '{$line->desc}', rowid: {$line->rowid})");
        }
        
        continue; // Ne pas traiter comme un produit
    }
    
    // Traiter uniquement les produits physiques (product_type = 0)
    if (empty($line->product_type) || $line->product_type == 0) {
        $total_products++;
        
        // Récupérer le détail JSON
        $detailJson = '';
        if (!empty($line->array_options['options_detailjson'])) {
            $detailJson = $line->array_options['options_detailjson'];
        }
        
        $details = array();
        if (!empty($detailJson)) {
            $decodedDetails = json_decode($detailJson, true);
            if (is_array($decodedDetails)) {
                foreach ($decodedDetails as $index => $detail) {
                    $detail['detail_id'] = 'detail_' . $line->rowid . '_' . $index;
                    $details[] = $detail;
                    $total_details++;
                }
            }
        }
        
        // Si pas de détails, créer un détail par défaut
        if (empty($details)) {
            $details[] = array(
                'detail_id' => 'detail_' . $line->rowid . '_0',
                'pieces' => (int)$line->qty,
                'longueur' => 1000,
                'largeur' => 1000,
                'total_value' => (float)$line->qty,
                'unit' => 'pieces',
                'description' => 'Produit standard'
            );
            $total_details++;
        }
        
        // Récupérer les infos du produit
        $product = new Product($db);
        $product_weight = 0;
        $product_weight_units = 0;
        
        if ($product->fetch($line->fk_product) > 0) {
            $product_weight = (float) $product->weight;
            $product_weight_units = (int) $product->weight_units;
            
            if ($debug_mode) {
                error_log("DEBUG COLISAGE - Produit ID {$line->fk_product}: Poids={$product_weight}, Unité={$product_weight_units}");
            }
        }
        
        $productId = 'prod_' . $line->rowid;
        $productInfo = array(
            'name' => $line->product_label ?: $product->label,
            'weight' => $product_weight,
            'weight_units' => $product_weight_units,
            'commandedet_id' => $line->rowid,
            'details' => $details
        );
        
        // Ajouter à la structure globale
        $productData[$productId] = $productInfo;
        
        // NOUVEAU : Ajouter à la section courante ou aux produits sans section
        if ($currentSection !== null) {
            $currentSection['produits'][] = $productId;
        } else {
            $produitsAvantPremierTitre[] = $productId;
        }
    }
}

// Sauvegarder la dernière section si elle existe
if ($currentSection !== null) {
    $sectionsData[] = $currentSection;
}

echo 'window.colisageData.productData = ' . json_encode($productData) . ';';

// NOUVEAU : Passer la structure hiérarchique au JavaScript
echo 'window.colisageData.sections = ' . json_encode($sectionsData) . ';';
echo 'window.colisageData.produitsAvantPremierTitre = ' . json_encode($produitsAvantPremierTitre) . ';';

// Informations de debug
if ($debug_mode) {
    echo 'window.colisageData.debugInfo = {';
    echo '  totalProducts: ' . $total_products . ',';
    echo '  totalDetails: ' . $total_details . ',';
    echo '  totalSections: ' . count($sectionsData) . ',';
    echo '  produitsAvantTitre: ' . count($produitsAvantPremierTitre) . ',';
    echo '  hasDetailModule: ' . ($total_details > $total_products ? 'true' : 'false') . ',';
    echo '  userIsAdmin: ' . ($user->admin ? 'true' : 'false');
    echo '};';
    
    // Debug des sections
    echo 'console.log("📊 DEBUG SECTIONS (Service ID=361):", window.colisageData.sections);';
}
?>

// Fonction de debug si activée
if (window.colisageData.debugMode) {
    console.log('🐛 DEBUG MODE ACTIVÉ - Version Optimisée (Service ID=361)');
    console.log('📊 Données Colisage:', window.colisageData);
    
    // Fonction pour afficher l'état dans la console
    window.debugColisageState = function() {
        console.group('🔍 État du module Colisage');
        console.log('Produits chargés:', Object.keys(window.colisageData.productData).length);
        console.log('Colis créés:', window.colisageApp ? window.colisageApp.packages.length : 0);
        console.log('Colis sélectionné:', window.colisageApp ? window.colisageApp.selectedPackageId : 'aucun');
        console.groupEnd();
    };
    
    // Auto-debug toutes les 10 secondes
    setInterval(window.debugColisageState, 10000);
}

console.log('✅ Données Colisage chargées (version optimisée avec Service ID=361)');
console.log('📦 Produits disponibles:', Object.keys(window.colisageData.productData).length);
console.log('🚀 Fonctionnalités: Auto-sauvegarde + Sélection rapide + Quantités rapides + Titres éditables');

// Message de démarrage
console.log('🎉 Module Colisage Optimisé initialisé !');
</script>

<?php
// Inclure le JavaScript principal avec édition des titres intégrée
$jsFile = dol_buildpath('/colisage/js/colisage.js', 1);
print '<script type="text/javascript" src="'.$jsFile.'?v='.time().'"></script>';

// Script pour indicateur de sauvegarde
?>
<script type="text/javascript">
// Fonction d'aide pour les utilisateurs
window.colisageHelp = function() {
    alert('Module Colisage - Aide rapide (Version Optimisée):\n\n' +
          '✨ NOUVELLES FONCTIONNALITÉS :\n' +
          '• Auto-sauvegarde : vos modifications sont sauvées automatiquement\n' +
          '• Boutons ➕ verts : sélection rapide d\'un produit depuis le récapitulatif\n' +
          '• Boutons ×4, ×6, ×8, Max : quantités rapides\n' +
          '• Titres éditables : cliquez sur ✏️ pour personnaliser les titres\n\n' +
          '📋 UTILISATION :\n' +
          '1. Créez un colis avec les boutons "+" en bas\n' +
          '2. Cliquez sur un colis dans la liste pour le sélectionner\n' +
          '3. Utilisez le bouton ➕ vert dans le récapitulatif pour sélection rapide\n' +
          '4. Ou utilisez les menus déroulants classiques\n' +
          '5. Choisissez une quantité avec les boutons rapides\n' +
          '6. Cliquez sur + pour ajouter (auto-sauvegarde)\n' +
          '7. Supprimez avec le bouton × rouge si besoin\n' +
          '8. Modifiez les titres avec l\'icône ✏️\n\n' +
          '💾 Pas besoin de sauvegarder manuellement, tout est automatique !');
};

// Ajouter un bouton d'aide pour tous les utilisateurs
setTimeout(function() {
    const header = document.querySelector('.colisage-header');
    if (header) {
        const helpBtn = document.createElement('button');
        helpBtn.textContent = '❓ Aide';
        helpBtn.style.cssText = 'position: absolute; top: 10px; right: 10px; background: #17a2b8; color: white; border: none; padding: 5px 10px; border-radius: 4px; cursor: pointer; font-size: 0.9rem;';
        helpBtn.onclick = window.colisageHelp;
        header.style.position = 'relative';
        header.appendChild(helpBtn);
    }
}, 1000);
</script>

<?php

llxFooter();
$db->close();
