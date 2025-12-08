<?php
/* Script de correction automatique pour le calcul de poids
 * Ce script corrige le fichier colisage.js pour utiliser la surface réelle
 */

echo "<h2>🔧 Correction Automatique du Calcul de Poids</h2>";

$jsFile = __DIR__ . '/js/colisage.js';

if (!file_exists($jsFile)) {
    die("❌ Fichier colisage.js non trouvé !");
}

// Lire le contenu actuel
$content = file_get_contents($jsFile);

// Rechercher et remplacer la logique de calcul
$oldCalculation = "// Poids pour cette ligne de détail = total_value × poids unitaire en kg
        const weightUnit = productWeightInKg * (detail.total_value || 1);";

$newCalculation = "// NOUVEAU CALCUL : Surface réelle de cette ligne de détail
        // Surface d'une pièce en m² = (longueur_mm × largeur_mm) / 1,000,000
        const surfaceUnitairePiece = (detail.longueur * detail.largeur) / 1000000; // m² par pièce
        const surfaceReelleLigne = surfaceUnitairePiece * quantity; // Surface totale de la ligne
        
        console.log('📐 CALCUL SURFACE RÉELLE:', {
            longueurMm: detail.longueur,
            largeurMm: detail.largeur,
            surfaceUnitairePiece: surfaceUnitairePiece.toFixed(6) + ' m²/pièce',
            quantite: quantity,
            surfaceReelleLigne: surfaceReelleLigne.toFixed(6) + ' m²',
            formule: `(${detail.longueur} × ${detail.largeur}) / 1,000,000 × ${quantity} = ${surfaceReelleLigne.toFixed(6)} m²`
        });
        
        // Poids pour cette ligne = surface_réelle × poids_produit_par_m²
        const weightUnit = productWeightInKg * surfaceReelleLigne;";

// Vérifier si le remplacement est nécessaire
if (strpos($content, 'surfaceReelleLigne') !== false) {
    echo "<div style='color: green; padding: 10px; background: #d4edda; border-radius: 5px;'>";
    echo "✅ Le fichier semble déjà corrigé (surface réelle détectée)";
    echo "</div>";
} else if (strpos($content, 'total_value') !== false) {
    echo "<div style='color: orange; padding: 10px; background: #fff3cd; border-radius: 5px;'>";
    echo "⚠️ Ancien calcul détecté (utilise total_value) - Correction nécessaire";
    echo "</div>";
    
    // Faire la correction
    $newContent = str_replace($oldCalculation, $newCalculation, $content);
    
    if ($newContent !== $content) {
        // Sauvegarder l'ancien fichier
        $backupFile = $jsFile . '.backup.' . date('Y-m-d_H-i-s');
        file_put_contents($backupFile, $content);
        
        // Écrire le nouveau contenu
        file_put_contents($jsFile, $newContent);
        
        echo "<div style='color: green; padding: 10px; background: #d4edda; border-radius: 5px; margin-top: 10px;'>";
        echo "✅ Correction appliquée avec succès !<br>";
        echo "📁 Sauvegarde créée : " . basename($backupFile);
        echo "</div>";
    } else {
        echo "<div style='color: red; padding: 10px; background: #f8d7da; border-radius: 5px; margin-top: 10px;'>";
        echo "❌ Impossible d'appliquer la correction automatiquement";
        echo "</div>";
    }
} else {
    echo "<div style='color: blue; padding: 10px; background: #d1ecf1; border-radius: 5px;'>";
    echo "ℹ️ État du fichier indéterminé - Vérification manuelle recommandée";
    echo "</div>";
}

// Aussi corriger le message de description
$oldComment = "// Poids ligne détail = total_value × poids_produit_en_kg";
$newComment = "// Poids ligne = surface_réelle × poids_produit_par_m²";

$content = file_get_contents($jsFile);
if (strpos($content, $oldComment) !== false) {
    $content = str_replace($oldComment, $newComment, $content);
    file_put_contents($jsFile, $content);
    echo "<p>✅ Commentaire de description également corrigé</p>";
}

echo "<h3>📋 Résumé de la Correction</h3>";
echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
echo "<tr style='background: #f0f0f0;'><th>Avant (Incorrect)</th><th>Après (Correct)</th></tr>";
echo "<tr>";
echo "<td style='padding: 10px; background: #f8d7da;'>";
echo "<strong>Formule :</strong> poids = poids_produit × total_value<br>";
echo "<strong>Problème :</strong> total_value peut ne pas correspondre à la surface réelle<br>";
echo "<strong>Exemple MOLENE :</strong> total_value = 100 (incorrect)";
echo "</td>";
echo "<td style='padding: 10px; background: #d4edda;'>";
echo "<strong>Formule :</strong> poids = poids_produit × surface_réelle<br>";
echo "<strong>Surface réelle :</strong> (longueur × largeur × quantité) / 1,000,000<br>";
echo "<strong>Exemple MOLENE :</strong> surface réelle = 1 m² (correct)";
echo "</td>";
echo "</tr>";
echo "</table>";

echo "<h3>✅ Vérification</h3>";
echo "<p>Pour vérifier que la correction fonctionne :</p>";
echo "<ol>";
echo "<li>Rafraîchissez l'interface du module (F5)</li>";
echo "<li>Activez le mode debug</li>";
echo "<li>Ajoutez un produit MOLENE au colis</li>";
echo "<li>Vérifiez dans la console que les logs affichent 'CALCUL SURFACE RÉELLE'</li>";
echo "<li>Les deux lignes MOLENE doivent maintenant avoir le même poids</li>";
echo "</ol>";

echo "<p><strong>Note :</strong> Cette correction s'applique uniquement au calcul JavaScript. Pour une correction permanente, modifiez également la logique PHP dans la classe ColisageItem.</p>";
?>
