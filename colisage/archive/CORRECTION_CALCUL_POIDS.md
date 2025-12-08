# Correction du Calcul de Poids - Module Colisage

## Problème Identifié

Le calcul de poids était complexe et ne respectait pas la logique métier simple :
- **Poids d'une ligne de détail** = `total_value × poids_produit_en_kg`
- **Poids d'un colis** = somme des poids de toutes les lignes de détail
- **Poids total** = poids du colis × multiplicateur

## Solution Implémentée

### 1. **Code PHP Corrigé** (`colisageitem.class.php`)

```php
// Convertir le poids du produit en kg selon l'unité
$product_weight = (float) $commandedet->weight;
$product_weight_units = (int) $commandedet->weight_units;

// Convertir en kg si nécessaire
$product_weight_kg = $product_weight;
if ($product_weight_units == 3) { // 3 = grammes dans Dolibarr
    $product_weight_kg = $product_weight / 1000;
}

$total_value = (float) ($detail['total_value'] ?? 1);
$this->weight_unit = $product_weight_kg * $total_value;
```

### 2. **Code JavaScript Corrigé** (`colisage.js`)

```javascript
// Convertir le poids du produit en kg selon l'unité
let productWeightInKg = product.weight;
if (product.weight_units === 3) { // 3 = grammes dans Dolibarr
    productWeightInKg = product.weight / 1000;
}

// Poids pour cette ligne de détail = total_value × poids unitaire en kg
const weightUnit = productWeightInKg * (detail.total_value || 1);
```

## Unités Dolibarr Gérées

| Code | Unité | Conversion |
|------|-------|------------|
| 0 ou vide | Kilogrammes (kg) | Aucune |
| 3 | Grammes (g) | ÷ 1000 |

## Exemples de Calcul

### Exemple 1 : Produit en kg
- **Produit** : 2.5 kg (weight_units = 0)
- **total_value** : 10.5
- **Poids ligne** : 2.5 × 10.5 = **26.25 kg**

### Exemple 2 : Produit en grammes
- **Produit** : 500 g (weight_units = 3)
- **Conversion** : 500 ÷ 1000 = 0.5 kg
- **total_value** : 8.2
- **Poids ligne** : 0.5 × 8.2 = **4.1 kg**

## Tests Disponibles

1. **Test PHP** : `test_calcul_poids.php`
2. **Test JavaScript** : Fonction `testWeightCalculation()` (bouton "🧪 Test Poids" en mode debug)

## Avantages de la Correction

- ✅ **Simplicité** : Formule unique et claire
- ✅ **Cohérence** : Même logique en PHP et JavaScript
- ✅ **Gestion des unités** : Support kg et grammes
- ✅ **Testabilité** : Tests automatisés inclus
- ✅ **Maintenabilité** : Code plus lisible

## Fichiers Modifiés

1. `class/colisageitem.class.php` - Méthode `prepareFromCommandeDetail()`
2. `js/colisage.js` - Fonction `addItem()`
3. `colisage_tab.php` - Ajout du bouton de test en mode debug
4. `test_calcul_poids.php` - Nouveau fichier de test

La logique est maintenant simplifiée et respecte exactement votre métier !
