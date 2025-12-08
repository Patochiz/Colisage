/* Copyright (C) 2025 Patrice GOURMELEN <pgourmelen@diamant-industrie.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * JavaScript pour le module Colisage - Version corrigée avec debug des IDs
 */

// Variables globales
let colisageApp = {
    productData: {},
    packages: [],
    nextPackageId: 1,
    selectedPackageId: null,
    commandeId: null,
    token: null,
    urlBase: ''
};

// Initialisation quand le DOM est prêt
document.addEventListener('DOMContentLoaded', function() {
    if (typeof window.colisageData !== 'undefined') {
        // Copier les données depuis la page PHP
        colisageApp.productData = window.colisageData.productData || {};
        colisageApp.commandeId = window.colisageData.commandeId;
        colisageApp.token = window.colisageData.token;
        colisageApp.urlBase = window.colisageData.urlBase;
        
        console.log('Colisage App initialisée:', colisageApp);
        
        // Charger les colis existants depuis la base
        loadExistingPackages();
        
        // Initialiser l'interface
        render();
        
        // Attacher l'événement du bouton sauvegarder
        const saveBtn = document.getElementById('save-btn');
        if (saveBtn) {
            saveBtn.addEventListener('click', saveColisage);
        }
    } else {
        console.error('Données Colisage non trouvées');
    }
});

/**
 * Charger les colis existants depuis la base de données
 */
function loadExistingPackages() {
    console.log('🔄 Chargement des colis existants...');
    
    const formData = new FormData();
    formData.append('action', 'load_colisage');
    formData.append('token', colisageApp.token);
    formData.append('commande_id', colisageApp.commandeId);
    
    fetch(colisageApp.urlBase + '/ajax/save_colisage.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        console.log('📥 Réponse AJAX reçue:', data);
        
        if (data.success && data.data && data.data.packages) {
            colisageApp.packages = data.data.packages;
            
            // DEBUG: Afficher les IDs des colis chargés
            console.log('📦 Colis chargés avec IDs:', colisageApp.packages.map(p => p.id));
            
            // Ajuster nextPackageId pour éviter les conflits
            if (colisageApp.packages.length > 0) {
                const maxId = Math.max(...colisageApp.packages.map(p => parseInt(p.id) || 0));
                colisageApp.nextPackageId = maxId + 1;
                console.log('🔢 NextPackageId ajusté à:', colisageApp.nextPackageId);
            }
            
            console.log('✅ Colis existants chargés:', colisageApp.packages.length);
        } else {
            console.log('ℹ️ Aucun colis existant trouvé');
            colisageApp.packages = [];
        }
        
        render();
    })
    .catch(error => {
        console.error('❌ Erreur lors du chargement:', error);
        colisageApp.packages = [];
        render();
    });
}

/**
 * Fonction pour vérifier s'il reste des produits disponibles
 */
function hasAvailableProducts() {
    return Object.entries(colisageApp.productData).some(([productId, product]) => {
        return product.details.some((detail, index) => {
            return getAvailableQuantity(productId, index) > 0;
        });
    });
}

/**
 * Calculer le multiplicateur maximum autorisé pour un colis
 */
function getMaxMultiplier(packageId) {
    const pkg = colisageApp.packages.find(p => p.id == packageId); // == pour comparaison flexible
    if (!pkg || pkg.isFree || pkg.items.length === 0) {
        return 999; // Pas de limite pour les colis libres ou vides
    }

    let maxMultiplier = 999;

    pkg.items.forEach(item => {
        // Pour chaque article du colis, calculer combien de fois on peut le multiplier
        const productId = item.productId;
        const product = colisageApp.productData[productId];
        if (!product) return;
        
        const detailIndex = product.details.findIndex(d => d.detail_id === item.detailId);
        
        if (detailIndex !== -1) {
            const totalAvailable = product.details[detailIndex].pieces;
            
            // Calculer combien est utilisé par d'AUTRES colis (pas le colis actuel)
            let usedByOthers = 0;
            colisageApp.packages.forEach(otherPkg => {
                if (otherPkg.id != packageId && !otherPkg.isFree) { // != pour comparaison flexible
                    otherPkg.items.forEach(otherItem => {
                        if (otherItem.productId === productId && otherItem.detailId === item.detailId) {
                            usedByOthers += (otherItem.quantity * otherPkg.multiplier);
                        }
                    });
                }
            });
            
            const availableForThisPackage = totalAvailable - usedByOthers;
            const maxForThisItem = Math.floor(availableForThisPackage / item.quantity);
            maxMultiplier = Math.min(maxMultiplier, maxForThisItem);
        }
    });

    return Math.max(1, maxMultiplier); // Au minimum 1
}

/**
 * Calculer le nombre total de pièces restantes
 */
function getTotalRemainingPieces() {
    return Object.entries(colisageApp.productData).reduce((total, [productId, product]) => {
        return total + product.details.reduce((pSum, detail, index) => {
            return pSum + getAvailableQuantity(productId, index);
        }, 0);
    }, 0);
}

/**
 * Calculer la quantité utilisée d'un détail spécifique
 */
function getUsedQuantity(productId, detailIndex) {
    const product = colisageApp.productData[productId];
    if (!product || !product.details[detailIndex]) return 0;
    
    const detail = product.details[detailIndex];
    let totalUsed = 0;

    colisageApp.packages.forEach(pkg => {
        // Ne compter que les colis standard, pas les colis libres
        if (!pkg.isFree) {
            pkg.items.forEach(item => {
                if (item.productId === productId && item.detailId === detail.detail_id) {
                    totalUsed += (item.quantity * pkg.multiplier);
                }
            });
        }
    });

    return totalUsed;
}

/**
 * Calculer la quantité disponible d'un détail spécifique
 */
function getAvailableQuantity(productId, detailIndex) {
    const product = colisageApp.productData[productId];
    if (!product || !product.details[detailIndex]) return 0;
    
    const detail = product.details[detailIndex];
    return detail.pieces - getUsedQuantity(productId, detailIndex);
}

/**
 * Obtenir les options de produits disponibles pour un select
 */
function getAvailableProductsOptions() {
    return Object.entries(colisageApp.productData)
        .filter(([prodId, prod]) => {
            return prod.details.some((detail, index) => {
                return getAvailableQuantity(prodId, index) > 0;
            });
        })
        .map(([prodId, prod]) => `<option value="${prodId}">${prod.name}</option>`)
        .join('');
}

/**
 * Rendu de la section récapitulatif des produits - CORRECTION avec unités correctes
 */
function renderProductsSummary() {
    const container = document.getElementById('products-summary');
    if (!container) return;

    let html = '';
    
    Object.entries(colisageApp.productData).forEach(([productId, product]) => {
        html += `<div class="product-group">`;
        html += `<div class="product-group-title">${product.name}</div>`;
        
        product.details.forEach((detail, index) => {
            const used = getUsedQuantity(productId, index);
            const total = detail.pieces;
            const percentage = total > 0 ? (used / total) * 100 : 0;
            
            // CORRECTION : Affichage des dimensions selon le type de produit
            let dimensionsText = '';
            if (detail.largeur && detail.largeur !== null && detail.largeur > 0) {
                // Produit avec longueur ET largeur
                dimensionsText = `${detail.longueur} x ${detail.largeur}`;
            } else {
                // Produit avec longueur seulement (ex: ml, porteurs)
                dimensionsText = `${detail.longueur}`;
            }
            
            // CORRECTION : Utiliser la bonne unité et la bonne valeur
            let valueText = '';
            let unitText = detail.unit || 'pcs';
            
            if (detail.total_value && detail.total_value > 0) {
                // Utiliser total_value du détail JSON
                valueText = detail.total_value.toFixed(2);
            } else if (detail.largeur && detail.largeur !== null && detail.largeur > 0) {
                // Calcul en m² pour les produits avec longueur et largeur
                valueText = (detail.longueur * detail.largeur / 1000000).toFixed(2);
                unitText = 'm²';
            } else {
                // Produit linéaire ou autre
                valueText = (detail.longueur / 1000).toFixed(2);
                if (unitText === 'pcs') unitText = 'ml'; // Par défaut pour les produits linéaires
            }
            
            // Couleur du badge selon l'unité
            let badgeClass = 'surface-badge';
            if (unitText === 'ml') {
                badgeClass = 'length-badge';
            } else if (unitText === 'pcs') {
                badgeClass = 'pieces-badge';
            }
            
            html += `
                <div class="detail-line">
                    <div class="detail-info">
                        <div class="quantity-badge">
                            <span class="quantity-used">${used}</span> / 
                            <span class="quantity-total">${total}</span>
                        </div>
                        <div class="detail-progress">
                            <div class="detail-progress-bar ${percentage >= 100 ? 'detail-progress-complete' : ''}" 
                                 style="width: ${Math.min(percentage, 100)}%"></div>
                        </div>
                        <div class="detail-specs">${dimensionsText}</div>
                        <div class="${badgeClass}">${valueText} ${unitText}</div>
                        <div class="desc-badge">${detail.description || 'N/A'}</div>
                    </div>
                </div>
            `;
        });
        
        html += `</div>`;
    });

    container.innerHTML = html;
}

/**
 * Rendu de la section récapitulatif des colis - CORRECTION avec debug des IDs
 */
function renderColisSummary() {
    const container = document.getElementById('colis-summary');
    if (!container) return;

    const hasProducts = hasAvailableProducts();
    const remainingPieces = getTotalRemainingPieces();
    
    // DEBUG: Afficher les IDs avant le rendu
    console.log('🎨 Rendu des colis. IDs disponibles:', colisageApp.packages.map(p => `${p.id} (${typeof p.id})`));
    console.log('🎯 Colis sélectionné:', colisageApp.selectedPackageId, '(', typeof colisageApp.selectedPackageId, ')');
    
    if (colisageApp.packages.length === 0) {
        container.innerHTML = `
            <div class="colis-actions">
                <button class="btn-new-colis ${!hasProducts ? 'btn-disabled' : ''}" 
                        onclick="createNewPackage()" 
                        ${!hasProducts ? 'disabled title="Plus de produits disponibles"' : ''}>
                    + Colis standard
                </button>
                <button class="btn-free-colis" onclick="createFreePackage()">+ Colis libre</button>
            </div>
            <div style="text-align: center; padding: 2rem 1rem; color: #6c757d; font-style: italic;">
                ${hasProducts ? 'Aucun colis créé' : `Stock épuisé - ${remainingPieces} pièces restantes`}
            </div>
        `;
        return;
    }

    let html = `
        <div class="colis-actions" style="margin-bottom: 1rem;">
            <button class="btn-new-colis ${!hasProducts ? 'btn-disabled' : ''}" 
                    onclick="createNewPackage()" 
                    ${!hasProducts ? 'disabled title="Plus de produits disponibles"' : ''}>
                + Colis standard
            </button>
            <button class="btn-free-colis" onclick="createFreePackage()">+ Colis libre</button>
        </div>
        
        ${!hasProducts ? `
            <div style="background: #fff3cd; color: #856404; padding: 0.5rem; border-radius: 4px; margin-bottom: 1rem; font-size: 0.85rem; text-align: center;">
                ⚠️ Tous les produits ont été colisés (${remainingPieces} pièces restantes)
            </div>
        ` : ''}
        
        <div class="colis-list">
    `;
    
    colisageApp.packages.forEach((pkg, index) => {
        const totalWeight = pkg.items.reduce((sum, item) => sum + (item.quantity * item.weight), 0);
        const totalSurface = pkg.items.reduce((sum, item) => 
            sum + (item.quantity * item.longueur * item.largeur / 1000000), 0);
        
        // DEBUG: Vérifier la correspondance des IDs
        const isSelected = colisageApp.selectedPackageId == pkg.id; // == pour comparaison flexible
        console.log(`📦 Colis #${pkg.id} - Sélectionné: ${isSelected} (selectedId: ${colisageApp.selectedPackageId})`);
        
        html += `
            <div class="colis-item ${isSelected ? 'selected' : ''}" 
                 onclick="selectPackage('${pkg.id}')" 
                 data-package-id="${pkg.id}">
                <div class="colis-item-header">
                    <div class="colis-item-left">
                        <div class="colis-multiplier">${pkg.multiplier}×</div>
                        ${pkg.isFree ? '<div class="colis-type-badge">LIBRE</div>' : ''}
                        <div class="colis-title">Colis #${pkg.id}</div>
                        <div class="colis-stats">
                            <span>Poids: <strong>${(totalWeight * pkg.multiplier).toFixed(1)} kg</strong></span>
                            <span>Surface: <strong>${(totalSurface * pkg.multiplier).toFixed(2)} m²</strong></span>
                            <span>Articles: <strong>${pkg.items.length}</strong></span>
                        </div>
                    </div>
                    <button class="btn-delete-colis" onclick="handleDeletePackage(event, '${pkg.id}')" title="Supprimer ce colis">×</button>
                </div>
        `;
        
        if (pkg.items.length > 0) {
            html += `<div class="colis-content">`;
            pkg.items.forEach(item => {
                const productName = item.productId.startsWith('prod_') 
                    ? colisageApp.productData[item.productId]?.name || 'Produit inconnu'
                    : item.customName || 'Article libre';
                    
                html += `
                    <div class="colis-detail-item">
                        <strong>${productName}</strong>
                        <span>${item.quantity} × ${item.longueur}×${item.largeur}</span>
                        <span class="colis-detail-surface">${(item.quantity * item.longueur * item.largeur / 1000000).toFixed(2)} m²</span>
                        <span class="colis-detail-desc">${item.description}</span>
                    </div>
                `;
            });
            html += `</div>`;
        }
        
        html += `</div>`;
    });
    
    html += `</div>`;

    container.innerHTML = html;
}

/**
 * Rendu de l'éditeur de colis - CORRECTION avec recherche flexible des IDs
 */
function renderColisEditor() {
    const container = document.getElementById('colis-editor');
    if (!container) return;

    if (!colisageApp.selectedPackageId) {
        container.innerHTML = `
            <div class="empty-editor">
                <h3>Aucun colis sélectionné</h3>
                <p>Sélectionnez un colis dans la liste de droite pour le modifier</p>
            </div>
        `;
        return;
    }

    // CORRECTION: Recherche flexible avec comparaison == au lieu de ===
    const pkg = colisageApp.packages.find(p => p.id == colisageApp.selectedPackageId);
    if (!pkg) {
        console.error('❌ Colis non trouvé:', colisageApp.selectedPackageId);
        console.log('📋 Colis disponibles:', colisageApp.packages.map(p => ({id: p.id, type: typeof p.id})));
        
        // Réessayer avec conversion de type
        const pkgWithConversion = colisageApp.packages.find(p => String(p.id) === String(colisageApp.selectedPackageId));
        if (pkgWithConversion) {
            console.log('✅ Colis trouvé après conversion de type');
            colisageApp.selectedPackageId = pkgWithConversion.id; // Synchroniser les types
            renderColisEditor(); // Relancer le rendu
            return;
        }
        
        // Si toujours pas trouvé, reset la sélection
        colisageApp.selectedPackageId = null;
        renderColisEditor();
        return;
    }

    const totalWeight = pkg.items.reduce((sum, item) => sum + (item.quantity * item.weight), 0);
    const totalSurface = pkg.items.reduce((sum, item) => 
        sum + (item.quantity * item.longueur * item.largeur / 1000000), 0);
    const maxMultiplier = getMaxMultiplier(colisageApp.selectedPackageId);

    let html = `
        <div class="colis-editor-header">
            <div class="colis-editor-title">
                Édition du Colis #${pkg.id}
                ${pkg.isFree ? '<div class="colis-type-badge">LIBRE</div>' : ''}
            </div>
            <div class="multiplier-control">
                <span>Quantité:</span>
                <input type="number" value="${pkg.multiplier}" min="1" ${!pkg.isFree && pkg.items.length > 0 ? `max="${maxMultiplier}"` : ''} 
                       class="multiplier-input" 
                       onchange="updateMultiplier('${pkg.id}', this.value)"
                       oninput="validateMultiplier(this, ${maxMultiplier})"
                       ${!pkg.isFree && pkg.items.length > 0 ? `title="Maximum: ${maxMultiplier} colis"` : ''}>
                <span>colis</span>
                ${!pkg.isFree && pkg.items.length > 0 && maxMultiplier < 999 ? `<span style="font-size: 0.8rem; color: #6c757d;">(max: ${maxMultiplier})</span>` : ''}
            </div>
        </div>
        <div class="colis-editor-content">
            <div style="display: flex; gap: 2rem; margin-bottom: 1rem; font-size: 0.9rem; color: #6c757d;">
                <div>Poids/colis: <strong>${totalWeight.toFixed(1)} kg</strong></div>
                <div>Surface/colis: <strong>${totalSurface.toFixed(2)} m²</strong></div>
                <div>Total: <strong>${(totalSurface * pkg.multiplier).toFixed(2)} m²</strong></div>
            </div>
            
            <table class="colis-table">
                <thead>
                    <tr>
                        <th>${pkg.isFree ? 'Description' : 'Produit'}</th>
                        <th>Détail</th>
                        <th style="width: 120px;">Action</th>
                    </tr>
                </thead>
                <tbody>
    `;
    
    // Afficher les articles existants
    pkg.items.forEach((item, index) => {
        const productName = item.productId.startsWith('prod_') 
            ? colisageApp.productData[item.productId]?.name || 'Produit inconnu'
            : item.customName || 'Article libre';
            
        html += `
            <tr class="item-row">
                <td><strong>${productName}</strong></td>
                <td>
                    <div class="item-detail">
                        <span>${item.quantity} × ${item.longueur}×${item.largeur}</span>
                        <span class="item-surface">${(item.quantity * item.longueur * item.largeur / 1000000).toFixed(2)} m²</span>
                        <span class="item-desc">${item.description}</span>
                        <span class="item-weight">Poids: ${(item.weight * item.quantity).toFixed(2)} kg</span>
                    </div>
                </td>
                <td>
                    <button class="delete-item-btn" onclick="removeItem('${pkg.id}', ${index})" title="Supprimer cet article">×</button>
                </td>
            </tr>
        `;
    });
    
    // Ligne d'ajout
    if (pkg.isFree) {
        html += `
            <tr class="add-row">
                <td>
                    <input type="text" class="form-select" id="custom-name-${pkg.id}" placeholder="Nom de l'article" style="width: 100%;">
                </td>
                <td>
                    <div class="free-form-row">
                        <input type="number" class="qty-input" id="qty-input-${pkg.id}" placeholder="Qté" min="1" style="width: 50px;">
                        <span>×</span>
                        <input type="number" class="qty-input" id="length-input-${pkg.id}" placeholder="L" min="1" style="width: 60px;">
                        <span>×</span>
                        <input type="number" class="qty-input" id="width-input-${pkg.id}" placeholder="l" min="1" style="width: 60px;">
                        <input type="text" class="form-select" id="desc-input-${pkg.id}" placeholder="Description" style="width: 80px;">
                        <input type="number" class="qty-input" id="weight-input-${pkg.id}" placeholder="Poids/u" step="0.1" style="width: 60px;">
                    </div>
                </td>
                <td>
                    <button class="add-btn" onclick="addFreeItem('${pkg.id}')">+</button>
                </td>
            </tr>
        `;
    } else if (hasAvailableProducts()) {
        html += `
            <tr class="add-row">
                <td>
                    <select class="form-select" id="product-select-${pkg.id}" onchange="handleProductChange('${pkg.id}', this.value)">
                        <option value="">Choisir produit...</option>
                        ${getAvailableProductsOptions()}
                    </select>
                </td>
                <td>
                    <select class="form-select" id="detail-select-${pkg.id}" disabled onchange="handleDetailChange('${pkg.id}', this.value)">
                        <option value="">Choisir détail...</option>
                    </select>
                </td>
                <td>
                    <input type="number" class="qty-input" id="qty-input-${pkg.id}" placeholder="Qté" min="1" disabled 
                           oninput="validateQuantity(this)">
                    <button class="add-btn" onclick="addItem('${pkg.id}')">+</button>
                </td>
            </tr>
        `;
    } else {
        html += `
            <tr class="add-row">
                <td colspan="3" style="text-align: center; padding: 1rem; color: #856404; background: #fff3cd; border-radius: 4px;">
                    ⚠️ Plus de produits disponibles - Tous les articles ont été colisés
                </td>
            </tr>
        `;
    }
    
    html += `
                </tbody>
            </table>
        </div>
    `;

    container.innerHTML = html;
}

/**
 * Rendu complet de l'interface
 */
function render() {
    renderProductsSummary();
    renderColisSummary();
    renderColisEditor();
}

/**
 * Créer un nouveau colis standard
 */
function createNewPackage() {
    if (!hasAvailableProducts()) {
        console.log('Impossible de créer un nouveau colis standard : plus de produits disponibles');
        return;
    }
    
    const newPkg = {
        id: colisageApp.nextPackageId++,
        multiplier: 1,
        items: [],
        isFree: false
    };
    colisageApp.packages.push(newPkg);
    colisageApp.selectedPackageId = newPkg.id;
    console.log('✅ Nouveau colis standard créé avec ID:', newPkg.id);
    render();
}

/**
 * Créer un nouveau colis libre
 */
function createFreePackage() {
    const newPkg = {
        id: colisageApp.nextPackageId++,
        multiplier: 1,
        items: [],
        isFree: true
    };
    colisageApp.packages.push(newPkg);
    colisageApp.selectedPackageId = newPkg.id;
    console.log('✅ Nouveau colis libre créé avec ID:', newPkg.id);
    render();
}

/**
 * Sélectionner un colis - CORRECTION avec debug et gestion des types
 */
function selectPackage(packageId) {
    console.log('🎯 Sélection du colis:', packageId, '(type:', typeof packageId, ')');
    
    // Vérifier que le colis existe
    const pkg = colisageApp.packages.find(p => p.id == packageId); // == pour comparaison flexible
    if (!pkg) {
        console.error('❌ Colis non trouvé lors de la sélection');
        console.log('📋 Colis disponibles:', colisageApp.packages.map(p => ({id: p.id, type: typeof p.id})));
        return;
    }
    
    colisageApp.selectedPackageId = packageId;
    console.log('✅ Colis sélectionné:', packageId);
    render();
}

/**
 * Supprimer un colis - CORRECTION avec sauvegarde automatique
 */
function handleDeletePackage(event, packageId) {
    // Arrêter la propagation de l'événement pour éviter la sélection du colis
    if (event) {
        event.stopPropagation();
        event.preventDefault();
    }
    
    console.log('🗑️ Suppression colis ID:', packageId, '(type:', typeof packageId, ')');
    
    // Confirmation avant suppression
    if (!confirm('Êtes-vous sûr de vouloir supprimer ce colis ?')) {
        return;
    }
    
    // Supprimer le colis du tableau avec comparaison flexible
    const originalLength = colisageApp.packages.length;
    colisageApp.packages = colisageApp.packages.filter(p => p.id != packageId); // != pour comparaison flexible
    
    console.log(`✅ Colis supprimé. Avant: ${originalLength}, Après: ${colisageApp.packages.length}`);
    
    // Si le colis supprimé était sélectionné, sélectionner un autre colis ou aucun
    if (colisageApp.selectedPackageId == packageId) { // == pour comparaison flexible
        colisageApp.selectedPackageId = colisageApp.packages.length > 0 ? colisageApp.packages[0].id : null;
        console.log('🎯 Nouveau colis sélectionné:', colisageApp.selectedPackageId);
    }
    
    // CORRECTION : Sauvegarder automatiquement après suppression
    console.log('💾 Sauvegarde automatique après suppression...');
    
    render(); // Mettre à jour l'interface immédiatement
    
    // Sauvegarder en base de données
    saveColisageAfterDelete(packageId);
}

/**
 * Sauvegarder le colisage après suppression d'un colis
 */
function saveColisageAfterDelete(deletedPackageId) {
    if (colisageApp.packages.length === 0) {
        console.log('💾 Sauvegarde de la suppression (aucun colis restant)');
    } else {
        console.log('💾 Sauvegarde de la suppression (colis restants:', colisageApp.packages.length, ')');
    }
    
    // Afficher un indicateur de sauvegarde
    const savingMessage = showMessage('<span class="loading-spinner"></span> Sauvegarde de la suppression...', 'info', 0);
    
    // Utiliser FormData pour l'envoi
    const formData = new FormData();
    formData.append('action', 'save_colisage');
    formData.append('token', colisageApp.token);
    formData.append('commande_id', colisageApp.commandeId);
    formData.append('packages', JSON.stringify(colisageApp.packages));
    
    fetch(colisageApp.urlBase + '/ajax/save_colisage.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        // Supprimer le message de sauvegarde
        if (savingMessage && savingMessage.parentNode) {
            savingMessage.parentNode.removeChild(savingMessage);
        }
        
        if (data.success) {
            console.log('✅ Suppression sauvegardée avec succès');
            showMessage('✅ Colis supprimé et sauvegardé !', 'success', 3000);
        } else {
            console.error('❌ Erreur lors de la sauvegarde de la suppression:', data.error);
            showMessage('❌ Erreur lors de la sauvegarde: ' + data.error + '<br><small>Rechargement automatique dans 3 secondes...</small>', 'error', 0);
            // En cas d'erreur, recharger la page pour récupérer l'état cohérent
            setTimeout(() => {
                window.location.reload();
            }, 3000);
        }
    })
    .catch(error => {
        // Supprimer le message de sauvegarde
        if (savingMessage && savingMessage.parentNode) {
            savingMessage.parentNode.removeChild(savingMessage);
        }
        
        console.error('❌ Erreur de communication lors de la suppression:', error);
        showMessage('❌ Erreur de communication - Rechargement automatique dans 3 secondes...', 'error', 0);
        // En cas d'erreur réseau, recharger la page
        setTimeout(() => {
            window.location.reload();
        }, 3000);
    });
}

/**
 * Mettre à jour le multiplicateur d'un colis
 */
function updateMultiplier(packageId, value) {
    const pkg = colisageApp.packages.find(p => p.id == packageId); // == pour comparaison flexible
    if (pkg) {
        pkg.multiplier = Math.max(1, parseInt(value) || 1);
        render();
    }
}

/**
 * Valider le multiplicateur
 */
function validateMultiplier(input, maxAllowed) {
    const value = parseInt(input.value);
    
    if (value > maxAllowed && maxAllowed < 999) {
        input.value = maxAllowed;
        console.log(`Multiplicateur automatiquement limité à ${maxAllowed} colis maximum (stock insuffisant)`);
    }
}

/**
 * Gérer le changement de produit dans le formulaire d'ajout
 */
function handleProductChange(packageId, productId) {
    const detailSelect = document.getElementById(`detail-select-${packageId}`);
    const qtyInput = document.getElementById(`qty-input-${packageId}`);
    const pkg = colisageApp.packages.find(p => p.id == packageId); // == pour comparaison flexible
    
    detailSelect.innerHTML = '<option value="">Choisir détail...</option>';
    detailSelect.disabled = !productId;
    
    // Reset et désactiver l'input quantité
    qtyInput.value = '';
    qtyInput.max = '';
    qtyInput.disabled = !productId;

    if (productId) {
        const product = colisageApp.productData[productId];
        product.details.forEach((detail, index) => {
            const available = getAvailableQuantity(productId, index);
            if (available > 0) {
                const maxPerColis = Math.floor(available / pkg.multiplier);
                const option = document.createElement('option');
                option.value = detail.detail_id;
                option.dataset.maxPerColis = maxPerColis;
                if (pkg.multiplier > 1) {
                    option.textContent = `${maxPerColis} max par colis (${available} total) - ${detail.longueur}×${detail.largeur}mm (${detail.description || 'N/A'})`;
                } else {
                    option.textContent = `${available} restants sur ${detail.pieces} - ${detail.longueur}×${detail.largeur}mm (${detail.description || 'N/A'})`;
                }
                detailSelect.appendChild(option);
            }
        });
    }
}

/**
 * Gérer le changement de détail dans le formulaire d'ajout
 */
function handleDetailChange(packageId, detailId) {
    const detailSelect = document.getElementById(`detail-select-${packageId}`);
    const qtyInput = document.getElementById(`qty-input-${packageId}`);
    
    if (!detailId) {
        qtyInput.value = '';
        qtyInput.max = '';
        qtyInput.disabled = true;
    } else {
        const selectedOption = detailSelect.options[detailSelect.selectedIndex];
        const maxPerColis = parseInt(selectedOption.dataset.maxPerColis);
        
        qtyInput.max = maxPerColis;
        qtyInput.disabled = false;
        qtyInput.placeholder = `Max: ${maxPerColis}`;
        
        // Si la valeur actuelle dépasse le max, la réinitialiser
        if (parseInt(qtyInput.value) > maxPerColis) {
            qtyInput.value = '';
        }
    }
}

/**
 * Valider la quantité saisie
 */
function validateQuantity(input) {
    const value = parseInt(input.value);
    const max = parseInt(input.max);
    
    if (value > max && max > 0) {
        input.value = max;
        console.log(`Quantité automatiquement limitée à ${max} pièces maximum`);
    }
}

/**
 * Ajouter un article standard à un colis
 */
function addItem(packageId) {
    const productSelect = document.getElementById(`product-select-${packageId}`);
    const detailSelect = document.getElementById(`detail-select-${packageId}`);
    const qtyInput = document.getElementById(`qty-input-${packageId}`);
    const pkg = colisageApp.packages.find(p => p.id == packageId); // == pour comparaison flexible

    const productId = productSelect.value;
    const detailId = detailSelect.value;
    const quantity = parseInt(qtyInput.value);
    const maxAllowed = parseInt(qtyInput.max);

    if (!productId || !detailId || !quantity || quantity <= 0) {
        console.log('Erreur: Veuillez remplir tous les champs');
        return;
    }

    if (quantity > maxAllowed) {
        console.log(`Erreur: Quantité trop élevée. Maximum autorisé: ${maxAllowed} pièces`);
        qtyInput.value = maxAllowed;
        return;
    }

    const product = colisageApp.productData[productId];
    const detail = product.details.find(d => d.detail_id === detailId);
    
    if (!detail) {
        console.log('Erreur: Détail non trouvé');
        return;
    }

    const existingItem = pkg.items.find(item => item.detailId === detailId);
    if (existingItem) {
        existingItem.quantity += quantity;
    } else {
        // CALCUL DU POIDS CORRIGÉ avec DEBUG :
        // Poids ligne détail = total_value × poids_produit_en_kg
        
        // DEBUG: Afficher les valeurs brutes
        console.log('🔍 DEBUG POIDS - Valeurs brutes:', {
            productName: product.name,
            productWeight: product.weight,
            productWeightUnits: product.weight_units,
            totalValue: detail.total_value,
            detailUnit: detail.unit
        });
        
        // Convertir le poids du produit en kg selon l'unité
        let productWeightInKg = product.weight;
        
        // CORRECTION: Gérer différents cas d'unités
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
        
        // VÉRIFICATION: Si le poids semble être en grammes (> 1000) mais unité = 0
        if ((product.weight_units === 0 || product.weight_units === undefined) && product.weight > 100) {
            console.warn('🚨 ATTENTION: Poids élevé avec unité kg - Possible erreur d\'unité!');
            console.log('💡 Suggestion: Le poids', product.weight, 'pourrait être en grammes');
            
            // Test: essayer la conversion en grammes
            const testWeightKg = product.weight / 1000;
            console.log('🧪 Test conversion grammes:', product.weight, 'g →', testWeightKg, 'kg');
            
            // CORRECTION TEMPORAIRE: Si poids > 100 et unité = kg, considérer comme grammes
            if (product.weight > 100 && confirm(`Le poids du produit semble élevé (${product.weight} kg).\nVoulez-vous le considérer comme des grammes ?\n\n${product.weight} g = ${testWeightKg} kg`)) {
                productWeightInKg = testWeightKg;
                console.log('✅ Correction appliquée: poids converti en kg');
            }
        }
        
        // Poids pour cette ligne de détail = total_value × poids unitaire en kg
        const weightUnit = productWeightInKg * (detail.total_value || 1);
        
        console.log('📊 CALCUL FINAL:', {
            productWeightInKg: productWeightInKg,
            totalValue: detail.total_value || 1,
            weightUnit: weightUnit,
            formula: `${productWeightInKg} kg × ${detail.total_value || 1} = ${weightUnit} kg`
        });
        
        pkg.items.push({
            productId: productId,
            detailId: detailId,
            quantity: quantity,
            longueur: detail.longueur,
            largeur: detail.largeur,
            description: detail.description || '',
            weight: weightUnit,
            customName: null
        });
    }

    // Vérifier si le multiplicateur actuel est encore valide
    const newMaxMultiplier = getMaxMultiplier(packageId);
    if (pkg.multiplier > newMaxMultiplier) {
        console.log(`Multiplicateur réduit de ${pkg.multiplier} à ${newMaxMultiplier} suite à l'ajout`);
        pkg.multiplier = newMaxMultiplier;
    }

    // Reset form
    productSelect.value = '';
    detailSelect.value = '';
    detailSelect.disabled = true;
    qtyInput.value = '';
    qtyInput.max = '';
    qtyInput.disabled = true;
    qtyInput.placeholder = 'Qté';

    render();
    console.log('Article ajouté avec succès');
}

/**
 * Ajouter un article libre à un colis
 */
function addFreeItem(packageId) {
    const customNameInput = document.getElementById(`custom-name-${packageId}`);
    const qtyInput = document.getElementById(`qty-input-${packageId}`);
    const lengthInput = document.getElementById(`length-input-${packageId}`);
    const widthInput = document.getElementById(`width-input-${packageId}`);
    const descInput = document.getElementById(`desc-input-${packageId}`);
    const weightInput = document.getElementById(`weight-input-${packageId}`);
    
    const pkg = colisageApp.packages.find(p => p.id == packageId); // == pour comparaison flexible

    const customName = customNameInput.value.trim();
    const quantity = parseInt(qtyInput.value);
    const longueur = parseInt(lengthInput.value);
    const largeur = parseInt(widthInput.value);
    const description = descInput.value.trim();
    const weight = parseFloat(weightInput.value) || 0;

    if (!customName || !quantity || quantity <= 0 || !longueur || longueur <= 0 || !largeur || largeur <= 0) {
        console.log('Erreur: Veuillez remplir tous les champs obligatoires (nom, quantité, longueur, largeur)');
        return;
    }

    // Ajouter l'article libre
    pkg.items.push({
        productId: 'free',
        detailId: 'free-' + Date.now(),
        customName: customName,
        quantity: quantity,
        longueur: longueur,
        largeur: largeur,
        description: description || 'Libre',
        weight: weight
    });

    // Reset form
    customNameInput.value = '';
    qtyInput.value = '';
    lengthInput.value = '';
    widthInput.value = '';
    descInput.value = '';
    weightInput.value = '';

    render();
    console.log('Article libre ajouté avec succès');
}

/**
 * Supprimer un article d'un colis
 */
function removeItem(packageId, itemIndex) {
    const pkg = colisageApp.packages.find(p => p.id == packageId); // == pour comparaison flexible
    pkg.items.splice(itemIndex, 1);
    
    // Si le colis est maintenant vide, remettre le multiplicateur à 1
    if (pkg.items.length === 0) {
        pkg.multiplier = 1;
    }
    
    render();
    console.log('Article supprimé');
}

/**
 * Sauvegarder le colisage
 */
function saveColisage() {
    if (colisageApp.packages.length === 0) {
        console.log('Aucun colis à enregistrer');
        return;
    }
    
    console.log('🔄 Début de la sauvegarde...');
    
    // Utiliser FormData pour l'envoi
    const formData = new FormData();
    formData.append('action', 'save_colisage');
    formData.append('token', colisageApp.token);
    formData.append('commande_id', colisageApp.commandeId);
    formData.append('packages', JSON.stringify(colisageApp.packages));
    
    fetch(colisageApp.urlBase + '/ajax/save_colisage.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            console.log('✅ Colisage sauvegardé avec succès');
            showMessage('Colisage sauvegardé avec succès !', 'success');
        } else {
            console.error('❌ Erreur lors de la sauvegarde:', data.error);
            showMessage('Erreur lors de la sauvegarde: ' + data.error, 'error');
        }
    })
    .catch(error => {
        console.error('❌ Erreur de communication:', error);
        showMessage('Erreur de communication avec le serveur', 'error');
    });
}

/**
 * Afficher un message à l'utilisateur - Version améliorée
 */
function showMessage(message, type = 'info', duration = 5000) {
    // Supprimer les anciens messages du même type
    const existingMessages = document.querySelectorAll(`.colisage-${type}`);
    existingMessages.forEach(msg => {
        if (msg.parentNode) {
            msg.parentNode.removeChild(msg);
        }
    });
    
    // Créer l'élément de message
    const messageDiv = document.createElement('div');
    messageDiv.className = `colisage-${type}`;
    messageDiv.innerHTML = message;
    
    // L'insérer au début du container
    const container = document.querySelector('.colisage-container');
    if (container) {
        container.insertBefore(messageDiv, container.firstChild);
        
        // Le supprimer après la durée spécifiée
        if (duration > 0) {
            setTimeout(() => {
                if (messageDiv.parentNode) {
                    messageDiv.parentNode.removeChild(messageDiv);
                }
            }, duration);
        }
    }
    
    return messageDiv;
}

// Fonction de test pour vérifier le calcul de poids
window.testWeightCalculation = function() {
    console.group('🧪 Test du Calcul de Poids');
    
    const testCases = [
        {
            name: 'Produit en Kg',
            product: { weight: 2.5, weight_units: 0 },
            detail: { total_value: 10.5 },
            expected: 26.25
        },
        {
            name: 'Produit en grammes',
            product: { weight: 500, weight_units: 3 },
            detail: { total_value: 8.2 },
            expected: 4.1
        },
        {
            name: 'Produit sans total_value',
            product: { weight: 1.2, weight_units: 0 },
            detail: { /* pas de total_value */ },
            expected: 1.2
        },
        {
            name: 'MOLENE (cas problématique)',
            product: { weight: 8.830, weight_units: 0 },
            detail: { total_value: 100 },
            expected: 883
        }
    ];
    
    testCases.forEach(test => {
        // Appliquer la logique corrigée
        let productWeightInKg = test.product.weight;
        if (test.product.weight_units === 3) {
            productWeightInKg = test.product.weight / 1000;
        }
        
        const weightUnit = productWeightInKg * (test.detail.total_value || 1);
        const isCorrect = Math.abs(weightUnit - test.expected) < 0.001;
        
        console.log(`${test.name}: ${isCorrect ? '✅' : '❌'} ` +
                   `Calculé: ${weightUnit.toFixed(3)} kg, ` +
                   `Attendu: ${test.expected} kg`);
                   
        // Alerte spéciale pour MOLENE
        if (test.name.includes('MOLENE') && !isCorrect) {
            console.warn('🚨 PROBLÈME DÉTECTÉ avec le produit MOLENE!');
            console.log('💡 Le poids du produit doit être vérifié dans la base de données');
        }
    });
    
    console.groupEnd();
};

// Fonction de correction automatique du poids
window.fixWeightIssue = function() {
    console.group('🔧 Correction Automatique du Poids');
    
    // Vérifier tous les produits chargés
    let issuesFound = 0;
    Object.entries(colisageApp.productData).forEach(([productId, product]) => {
        if (product.weight > 100 && (product.weight_units === 0 || product.weight_units === undefined)) {
            issuesFound++;
            console.warn(`⚠️ Produit suspect: ${product.name}`);
            console.log(`   Poids actuel: ${product.weight} (unité: ${product.weight_units})`);
            
            const correctedWeight = product.weight / 1000;
            console.log(`   Poids corrigé: ${correctedWeight} kg`);
            
            if (confirm(`Produit: ${product.name}\nPoids actuel: ${product.weight}\nCela semble être en grammes.\n\nCorriger en: ${correctedWeight} kg ?`)) {
                product.weight = correctedWeight;
                product.weight_units = 0; // Confirmer comme kg
                console.log(`✅ Correction appliquée pour ${product.name}`);
            }
        }
    });
    
    if (issuesFound === 0) {
        console.log('✅ Aucun problème de poids détecté');
        alert('Aucun problème de poids détecté dans les produits chargés.');
    } else {
        console.log(`🔧 Vérification terminée: ${issuesFound} produit(s) suspect(s)`);
        // Relancer le rendu après correction
        render();
    }
    
    console.groupEnd();
};

// Exposer les fonctions globalement pour les onclick
window.createNewPackage = createNewPackage;
window.createFreePackage = createFreePackage;
window.selectPackage = selectPackage;
window.handleDeletePackage = handleDeletePackage;
window.updateMultiplier = updateMultiplier;
window.validateMultiplier = validateMultiplier;
window.handleProductChange = handleProductChange;
window.handleDetailChange = handleDetailChange;
window.validateQuantity = validateQuantity;
window.addItem = addItem;
window.addFreeItem = addFreeItem;
window.removeItem = removeItem;
