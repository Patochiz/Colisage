/* Correction du calcul de poids - Version Surface Réelle
 * Ce fichier contient la nouvelle logique de calcul de poids
 * basée sur la surface réelle calculée et non sur total_value
 */

/**
 * Fonction de calcul de poids corrigée
 * @param {Object} product - Données du produit
 * @param {Object} detail - Détail de la ligne  
 * @param {number} quantity - Quantité sélectionnée
 * @returns {number} - Poids en kg
 */
function calculateCorrectWeight(product, detail, quantity) {
    console.group('🧮 CALCUL DE POIDS CORRIGÉ - SURFACE RÉELLE');
    
    // DEBUG: Afficher les valeurs brutes
    console.log('🔍 Valeurs d\'entrée:', {
        productName: product.name,
        productWeight: product.weight,
        productWeightUnits: product.weight_units,
        detailPieces: detail.pieces,
        detailLongueur: detail.longueur,
        detailLargeur: detail.largeur,
        detailTotalValue: detail.total_value,
        detailUnit: detail.unit,
        quantitySelected: quantity
    });
    
    // 1. Convertir le poids du produit en kg selon l'unité
    let productWeightInKg = product.weight;
    
    if (product.weight_units === 3) { 
        // 3 = grammes dans Dolibarr
        productWeightInKg = product.weight / 1000;
        console.log('🔄 Conversion grammes → kg:', product.weight, 'g →', productWeightInKg, 'kg');
    } else if (product.weight_units === 0 || product.weight_units === undefined || product.weight_units === null) {
        // 0 ou undefined = kg
        productWeightInKg = product.weight;
        console.log('✅ Poids déjà en kg:', productWeightInKg, 'kg');
    } else {
        // Autres unités non gérées
        console.warn('⚠️ Unité de poids non gérée:', product.weight_units);
        productWeightInKg = product.weight;
    }
    
    // 2. Vérification et correction automatique si poids suspect
    if ((product.weight_units === 0 || product.weight_units === undefined) && product.weight > 100) {
        console.warn('🚨 ATTENTION: Poids élevé avec unité kg - Possible erreur d\'unité!');
        const testWeightKg = product.weight / 1000;
        console.log('💡 Suggestion: Le poids', product.weight, 'pourrait être en grammes →', testWeightKg, 'kg');
        
        // Pour l'auto-correction, on utilise la conversion grammes
        productWeightInKg = testWeightKg;
        console.log('🔧 Auto-correction appliquée: poids converti en kg');
    }
    
    // 3. NOUVEAU CALCUL : Surface réelle de cette ligne de détail
    // Surface d'une pièce en m² = (longueur_mm × largeur_mm) / 1,000,000
    const surfaceUnitairePiece = (detail.longueur * detail.largeur) / 1000000; // m² par pièce
    const surfaceReelleLigne = surfaceUnitairePiece * quantity; // Surface totale de la ligne
    
    console.log('📐 CALCUL SURFACE RÉELLE:', {
        longueurMm: detail.longueur,
        largeurMm: detail.largeur,
        surfaceUnitairePiece: surfaceUnitairePiece.toFixed(6) + ' m²/pièce',
        quantiteSelectionnee: quantity,
        surfaceReelleLigne: surfaceReelleLigne.toFixed(6) + ' m²',
        formule: `(${detail.longueur} × ${detail.largeur}) / 1,000,000 × ${quantity} = ${surfaceReelleLigne.toFixed(6)} m²`
    });
    
    // 4. Poids pour cette ligne = surface_réelle × poids_produit_par_m²
    const weightUnit = productWeightInKg * surfaceReelleLigne;
    
    console.log('⚖️ CALCUL POIDS FINAL:', {
        productWeightInKg: productWeightInKg + ' kg/m²',
        surfaceReelleLigne: surfaceReelleLigne.toFixed(6) + ' m²',
        weightUnit: weightUnit.toFixed(3) + ' kg',
        formule: `${productWeightInKg} kg/m² × ${surfaceReelleLigne.toFixed(6)} m² = ${weightUnit.toFixed(3)} kg`
    });
    
    console.groupEnd();
    
    return weightUnit;
}

/**
 * Test de la fonction avec les données MOLENE
 */
function testMoleneCorrection() {
    console.log('🧪 TEST CORRECTION MOLENE');
    
    // Données d'exemple basées sur vos screenshots
    const productMolene = {
        name: 'MOLENE 200 1 Extérieur Acier 0.80 Prélaqué Gris RAL 7035 NP',
        weight: 8830, // En grammes dans la base (problème identifié)
        weight_units: 0 // Mais marqué comme kg (erreur)
    };
    
    const detail1 = {
        pieces: 2,
        longueur: 2500,
        largeur: 200,
        total_value: 100, // Cette valeur ne sera plus utilisée
        unit: 'm2',
        description: 'A2'
    };
    
    const detail2 = {
        pieces: 5,
        longueur: 1000,
        largeur: 200,
        total_value: 100, // Cette valeur ne sera plus utilisée
        unit: 'm2',
        description: 'A1'
    };
    
    console.log('\n🔬 Test Ligne 1 (2 × 2500×200):');
    const poids1 = calculateCorrectWeight(productMolene, detail1, 2);
    
    console.log('\n🔬 Test Ligne 2 (5 × 1000×200):');
    const poids2 = calculateCorrectWeight(productMolene, detail2, 5);
    
    console.log('\n📊 RÉSULTATS COMPARÉS:');
    console.log('Ligne 1 - Surface réelle:', ((2500 * 200) / 1000000 * 2).toFixed(6), 'm² - Poids:', poids1.toFixed(3), 'kg');
    console.log('Ligne 2 - Surface réelle:', ((1000 * 200) / 1000000 * 5).toFixed(6), 'm² - Poids:', poids2.toFixed(3), 'kg');
    
    // Les deux lignes ont la même surface réelle (1 m²), donc doivent avoir le même poids
    const surfaceReel1 = (2500 * 200) / 1000000 * 2;
    const surfaceReel2 = (1000 * 200) / 1000000 * 5;
    
    if (Math.abs(surfaceReel1 - surfaceReel2) < 0.001 && Math.abs(poids1 - poids2) < 0.001) {
        console.log('✅ CORRECTION RÉUSSIE: Les deux lignes ont maintenant le même poids!');
    } else {
        console.log('❌ Problème persistant...');
    }
}

// Fonction de remplacement à utiliser dans addItem()
function replaceWeightCalculationInAddItem() {
    return `
        // Utiliser la nouvelle fonction de calcul de poids
        const weightUnit = calculateCorrectWeight(product, detail, quantity);
    `;
}

// Test automatique
console.log('🚀 Chargement de la correction du calcul de poids...');
testMoleneCorrection();

// Instructions d'utilisation
console.log(`
📝 INSTRUCTIONS D'UTILISATION:

1. Dans la fonction addItem() de colisage.js, remplacer :
   const weightUnit = productWeightInKg * (detail.total_value || 1);

2. Par :
   const weightUnit = calculateCorrectWeight(product, detail, quantity);

3. Ajouter cette fonction calculateCorrectWeight() dans colisage.js

4. Le calcul utilisera maintenant la surface réelle calculée au lieu de total_value
`);
