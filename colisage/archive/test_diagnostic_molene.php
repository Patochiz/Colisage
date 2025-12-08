<?php
/* Test de diagnostic pour le problème de poids MOLENE
 * Ce fichier aide à identifier pourquoi le poids est incorrect
 */

echo "<h2>🔍 Diagnostic du Problème de Poids - Produit MOLENE</h2>";

// Simuler les données du produit MOLENE d'après les screenshots
$test_molene = [
    'name' => 'MOLENE 200 1 Extérieur Acier 0.80 Prélaqué Gris RAL 7035 NP',
    'weight_displayed' => '8.830 Kg', // Affiché dans la fiche produit
    'weight_value' => 8.830, // Valeur numérique si en kg
    'weight_value_alt' => 8830, // Valeur numérique si en grammes
    'total_value' => 100, // D'après "100 m²" dans le screenshot
    'expected_result' => '8.83 kg', // Résultat attendu
    'actual_result' => '88.30 kg' // Résultat affiché (problématique)
];

echo "<div style='background: #fff3cd; padding: 15px; border-radius: 5px; margin-bottom: 20px;'>";
echo "<h3>📋 Données du Problème</h3>";
echo "<p><strong>Produit :</strong> " . $test_molene['name'] . "</p>";
echo "<p><strong>Poids affiché fiche produit :</strong> " . $test_molene['weight_displayed'] . "</p>";
echo "<p><strong>Total_value :</strong> " . $test_molene['total_value'] . "</p>";
echo "<p><strong>Résultat attendu :</strong> " . $test_molene['expected_result'] . "</p>";
echo "<p><strong>Résultat affiché (incorrect) :</strong> " . $test_molene['actual_result'] . "</p>";
echo "</div>";

echo "<table border='1' style='border-collapse: collapse; width: 100%; margin-bottom: 20px;'>";
echo "<tr style='background: #f0f0f0;'>";
echo "<th>Hypothèse</th><th>Poids Produit</th><th>Unité</th><th>Conversion</th><th>Calcul</th><th>Résultat</th><th>Correct ?</th>";
echo "</tr>";

// Test des différentes hypothèses
$hypotheses = [
    [
        'name' => 'Poids en kg (8.830 kg)',
        'weight' => 8.830,
        'units' => 0, // kg
        'conversion' => 'Aucune',
        'formula' => '8.830 × 100',
        'result' => 8.830 * 100
    ],
    [
        'name' => 'Poids en grammes (8830 g)',
        'weight' => 8830,
        'units' => 3, // grammes
        'conversion' => '8830 ÷ 1000 = 8.83 kg',
        'formula' => '8.83 × 100',
        'result' => (8830 / 1000) * 100
    ],
    [
        'name' => 'Erreur: kg traités comme g',
        'weight' => 8.830,
        'units' => 0, // kg mais traité comme g
        'conversion' => '8.830 ÷ 10 = 0.883 kg',
        'formula' => '0.883 × 100',
        'result' => (8.830 / 10) * 100
    ],
    [
        'name' => 'Base de données en grammes',
        'weight' => 8830,
        'units' => 0, // stocké en g mais unité dit kg
        'conversion' => '8830 ÷ 1000 = 8.83 kg',
        'formula' => '8.83 × 100',
        'result' => (8830 / 1000) * 100
    ]
];

foreach ($hypotheses as $hyp) {
    $is_correct = abs($hyp['result'] - 883) < 1; // 8.83 kg × 100 = 883 kg attendu
    $is_matches_bug = abs($hyp['result'] - 88.3) < 1; // Match le bug observé
    
    $result_color = 'black';
    $status = '';
    
    if ($is_correct) {
        $result_color = 'green';
        $status = '✅ CORRECT';
    } elseif ($is_matches_bug) {
        $result_color = 'orange';
        $status = '🐛 CORRESPOND AU BUG';
    } else {
        $result_color = 'red';
        $status = '❌ INCORRECT';
    }
    
    echo "<tr>";
    echo "<td><strong>" . $hyp['name'] . "</strong></td>";
    echo "<td>" . $hyp['weight'] . "</td>";
    echo "<td>" . ($hyp['units'] == 0 ? 'kg' : 'g') . "</td>";
    echo "<td>" . $hyp['conversion'] . "</td>";
    echo "<td>" . $hyp['formula'] . "</td>";
    echo "<td style='color: $result_color; font-weight: bold;'>" . number_format($hyp['result'], 2) . " kg</td>";
    echo "<td style='color: $result_color; font-weight: bold;'>$status</td>";
    echo "</tr>";
}

echo "</table>";

echo "<div style='background: #d1ecf1; padding: 15px; border-radius: 5px; margin-bottom: 20px;'>";
echo "<h3>🔍 Diagnostic</h3>";
echo "<p>Le bug observé (88.30 kg au lieu de 883 kg attendu) suggère que :</p>";
echo "<ol>";
echo "<li><strong>Le poids est divisé par 10 quelque part</strong></li>";
echo "<li><strong>Ou il y a une erreur de décimales/unités</strong></li>";
echo "<li><strong>Le poids pourrait être stocké en grammes dans la base mais mal converti</strong></li>";
echo "</ol>";
echo "</div>";

echo "<div style='background: #d4edda; padding: 15px; border-radius: 5px;'>";
echo "<h3>✅ Solution Recommandée</h3>";
echo "<ol>";
echo "<li><strong>Activez le mode debug</strong> dans l'interface du module</li>";
echo "<li><strong>Ajoutez un article</strong> et observez la console JavaScript</li>";
echo "<li><strong>Vérifiez</strong> les valeurs affichées dans les logs debug</li>";
echo "<li><strong>Utilisez</strong> la boîte de dialogue de correction automatique qui apparaîtra</li>";
echo "</ol>";
echo "</div>";

echo "<h3>🧪 Test JavaScript Simulé</h3>";
echo "<script>";
echo "console.group('🔍 Test de Diagnostic MOLENE');";
echo "console.log('Produit:', '" . addslashes($test_molene['name']) . "');";
echo "console.log('Poids affiché fiche:', '" . $test_molene['weight_displayed'] . "');";
echo "console.log('Total value:', " . $test_molene['total_value'] . ");";
echo "console.log('Résultat attendu: 8.83 kg × 100 = 883 kg');";
echo "console.log('Résultat bugué observé:', '" . $test_molene['actual_result'] . "');";
echo "console.groupEnd();";
echo "</script>";

echo "<p><strong>Instructions :</strong> Ouvrez la console de votre navigateur (F12) pour voir les détails du test.</p>";
?>
