/* Copyright (C) 2025 Patrice GOURMELEN <pgourmelen@diamant-industrie.com>
 *
 * CORRECTION : Problème d'édition des colis - Version corrigée
 * 
 * Problème résolu : Les colis ne peuvent plus être édités après sélection
 * Cause : Problème de type d'ID (string vs number) et vérification stricte
 * Solution : Normalisation des IDs et vérification flexible
 */

/**
 * JavaScript pour le module Colisage - Version corrigée avec ÉDITION FONCTIONNELLE
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

/**
 * CORRECTION PRINCIPALE : Fonction de normalisation des IDs
 */
function normalizePackageId(id) {
    // Convertir en string pour une comparaison cohérente
    return String(id);
}

/**
 * CORRECTION PRINCIPALE : Trouver un colis par ID avec vérification flexible
 */
function findPackageById(packageId) {
    const normalizedId = normalizePackageId(packageId);
    
    // Essayer d'abord une correspondance exacte en string
    let pkg = colisageApp.packages.find(p => String(p.id) === normalizedId);
    
    // Si pas trouvé, essayer avec conversion numérique
    if (!pkg) {
        const numericId = parseInt(normalizedId);
        if (!isNaN(numericId)) {
            pkg = colisageApp.packages.find(p => parseInt(p.id) === numericId);
        }
    }
    
    if (pkg) {
        console.log(`✅ Colis trouvé: ID ${pkg.id} (recherché: ${packageId})`);
    } else {
        console.error(`❌ Colis non trouvé: ID ${packageId}`, {
            searchedId: packageId,
            normalizedId: normalizedId,
            availableIds: colisageApp.packages.map(p => ({id: p.id, type: typeof p.id}))
        });
    }
    
    return pkg;
}

/**
 * NOUVELLE FONCTION : Calculer les dimensions et unités correctes selon les règles
 */
function calculateDimensionsAndUnit(detail) {
    let dimensionsText = '';
    let valueText = '';
    let unitText = '';
    let badgeClass = '';
    
    // Vérifier la présence de longueur et largeur
    const hasLength = detail.longueur && detail.longueur !== null && detail.longueur > 0;
    const hasWidth = detail.largeur && detail.largeur !== null && detail.largeur > 0;
    
    if (hasLength && hasWidth) {
        // Produit avec longueur ET largeur → m²
        dimensionsText = `${detail.longueur} × ${detail.largeur}`;
        if (detail.total_value && detail.total_value > 0) {
            valueText = detail.total_value.toFixed(2);
        } else {
            valueText = (detail.longueur * detail.largeur / 1000000).toFixed(2);
        }
        unitText = 'm²';
        badgeClass = 'surface-badge';
    } else if (hasLength && !hasWidth) {
        // Produit avec longueur seulement → ml
        dimensionsText = `${detail.longueur}`;
        if (detail.total_value && detail.total_value > 0) {
            valueText = detail.total_value.toFixed(2);
        } else {
            valueText = (detail.longueur / 1000).toFixed(2);
        }
        unitText = 'ml';
        badgeClass = 'length-badge';
    } else {
        // Produit sans dimensions → unités (pièces)
        dimensionsText = 'Standard';
        if (detail.total_value && detail.total_value > 0) {
            valueText = detail.total_value.toString();
        } else {
            valueText = detail.pieces.toString();
        }
        unitText = 'u';
        badgeClass = 'pieces-badge';
    }
    
    return { dimensionsText, valueText, unitText, badgeClass };
}

/**
 * NOUVELLE FONCTION : Affichage intelligent des dimensions pour les articles
 */
function formatItemDimensions(item) {
    const hasLength = item.longueur && item.longueur !== null && item.longueur > 0;
    const hasWidth = item.largeur && item.largeur !== null && item.largeur > 0;
    
    if (hasLength && hasWidth) {
        // Affichage avec longueur et largeur
        return `${item.quantity} × ${item.longueur}×${item.largeur}`;
    } else if (hasLength && !hasWidth) {
        // Affichage avec longueur seulement
        return `${item.quantity} × ${item.longueur}`;
    } else {
        // Affichage en unités seulement
        return `${item.quantity} unités`;
    }
}

/**
 * NOUVELLE FONCTION : Calculer la surface ou quantité selon le type de produit
 */
function calculateItemValue(item) {
    const hasLength = item.longueur && item.longueur !== null && item.longueur > 0;
    const hasWidth = item.largeur && item.largeur !== null && item.largeur > 0;
    
    if (hasLength && hasWidth) {
        // Surface en m²
        const surface = (item.quantity * item.longueur * item.largeur / 1000000);
        return { value: surface.toFixed(2), unit: 'm²' };
    } else if (hasLength && !hasWidth) {
        // Longueur en ml
        const length = (item.quantity * item.longueur / 1000);
        return { value: length.toFixed(2), unit: 'ml' };
    } else {
        // Quantité en unités
        return { value: item.quantity.toString(), unit: 'u' };
    }
}

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
 * CORRECTION : Chargement des colis existants avec normalisation des IDs
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
            // CORRECTION : Normaliser les IDs des colis chargés
            colisageApp.packages = data.data.packages.map(pkg => {
                // Normaliser l'ID du colis
                pkg.id = normalizePackageId(pkg.id);
                return pkg;
            });
            
            // DEBUG: Afficher les IDs des colis chargés
            console.log('📦 Colis chargés avec IDs normalisés:', colisageApp.packages.map(p => p.id));
            
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
        showMessage('❌ Erreur lors du chargement des colis existants', 'error', 5000);
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
    const pkg = findPackageById(packageId);
    if (!pkg || pkg.isFree || pkg.items.length === 0) {
        return 999;
    }

    let maxMultiplier = 999;

    pkg.items.forEach(item => {
        const productId = item.productId;
        const product = colisageApp.productData[productId];
        if (!product) return;
        
        const detailIndex = product.details.findIndex(d => d.detail_id === item.detailId);
        
        if (detailIndex !== -1) {
            const totalAvailable = product.details[detailIndex].pieces;
            
            let usedByOthers = 0;
            colisageApp.packages.forEach(otherPkg => {
                if (normalizePackageId(otherPkg.id) !== normalizePackageId(packageId) && !otherPkg.isFree) {
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

    return Math.max(1, maxMultiplier);
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
 * Rendu de la section récapitulatif des produits - CORRECTION COMPLÈTE avec unités correctes
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
            
            // CORRECTION : Utiliser la nouvelle fonction pour les dimensions et unités
            const { dimensionsText, valueText, unitText, badgeClass } = calculateDimensionsAndUnit(detail);
            
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
 * Rendu de la section récapitulatif des colis - CORRECTION avec affichage intelligent
 */
function renderColisSummary() {
    const container = document.getElementById('colis-summary');
    if (!container) return;

    const hasProducts = hasAvailableProducts();
    const remainingPieces = getTotalRemainingPieces();
    
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
        // CORRECTION : Utiliser le calcul de poids simplifié
        const totalWeight = calculatePackageWeight(pkg);
        
        // CORRECTION : Calcul intelligent de la surface totale selon le type de produits
        let totalValue = 0;
        let mainUnit = 'm²'; // Unité par défaut
        
        pkg.items.forEach(item => {
            const itemValue = calculateItemValue(item);
            totalValue += parseFloat(itemValue.value);
            // Prendre l'unité du premier article comme référence
            if (pkg.items.indexOf(item) === 0) {
                mainUnit = itemValue.unit;
            }
        });
        
        const isSelected = normalizePackageId(colisageApp.selectedPackageId) === normalizePackageId(pkg.id);
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
                            <span>${mainUnit === 'm²' ? 'Surface' : (mainUnit === 'ml' ? 'Longueur' : 'Quantité')}: <strong>${(totalValue * pkg.multiplier).toFixed(2)} ${mainUnit}</strong></span>
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
                
                // CORRECTION : Affichage intelligent des dimensions
                const dimensionsText = formatItemDimensions(item);
                const itemValue = calculateItemValue(item);
                    
                html += `
                    <div class="colis-detail-item">
                        <strong>${productName}</strong>
                        <span>${dimensionsText}</span>
                        <span class="colis-detail-surface">${itemValue.value} ${itemValue.unit}</span>
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
 * CORRECTION MAJEURE : Rendu de l'éditeur de colis avec vérification améliorée
 */
function renderColisEditor() {
    const container = document.getElementById('colis-editor');
    if (!container) {
        console.error('❌ Conteneur éditeur non trouvé');
        return;
    }

    console.log('🎨 Rendu de l\'éditeur - Colis sélectionné:', colisageApp.selectedPackageId);

    if (!colisageApp.selectedPackageId) {
        container.innerHTML = `
            <div class="empty-editor">
                <h3>Aucun colis sélectionné</h3>
                <p>Cliquez sur un colis dans la liste de droite pour le modifier</p>
            </div>
        `;
        console.log('ℹ️ Éditeur vide affiché (aucun colis sélectionné)');
        return;
    }

    // CORRECTION : Utiliser la fonction améliorée pour trouver le colis
    const pkg = findPackageById(colisageApp.selectedPackageId);
    
    if (!pkg) {
        console.error('❌ Erreur critique: Colis sélectionné non trouvé lors du rendu');
        
        // Réinitialiser la sélection
        colisageApp.selectedPackageId = null;
        
        container.innerHTML = `
            <div class="empty-editor" style="background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb;">
                <h3>❌ Erreur de sélection</h3>
                <p>Le colis sélectionné n'existe plus. Sélectionnez un autre colis.</p>
                <button onclick="window.location.reload()" style="margin-top: 10px; padding: 5px 15px; background: #dc3545; color: white; border: none; border-radius: 4px; cursor: pointer;">
                    🔄 Recharger la page
                </button>
            </div>
        `;
        return;
    }

    console.log('✅ Colis trouvé pour l\'édition:', pkg.id, '- Articles:', pkg.items.length);

    // CORRECTION : Utiliser le calcul de poids simplifié
    const totalWeight = calculatePackageWeight(pkg);
    
    // Calcul intelligent de la valeur totale du colis
    let totalValue = 0;
    let mainUnit = 'm²'; // Unité par défaut
    
    pkg.items.forEach(item => {
        const itemValue = calculateItemValue(item);
        totalValue += parseFloat(itemValue.value);
        if (pkg.items.indexOf(item) === 0) {
            mainUnit = itemValue.unit;
        }
    });
    
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
                <div>${mainUnit === 'm²' ? 'Surface' : (mainUnit === 'ml' ? 'Longueur' : 'Quantité')}/colis: <strong>${totalValue.toFixed(2)} ${mainUnit}</strong></div>
                <div>Total: <strong>${(totalValue * pkg.multiplier).toFixed(2)} ${mainUnit}</strong></div>
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
    
    // Afficher les articles existants avec affichage intelligent
    pkg.items.forEach((item, index) => {
        const productName = item.productId.startsWith('prod_') 
            ? colisageApp.productData[item.productId]?.name || 'Produit inconnu'
            : item.customName || 'Article libre';
        
        // CORRECTION : Affichage intelligent des dimensions
        const dimensionsText = formatItemDimensions(item);
        const itemValue = calculateItemValue(item);
            
        html += `
            <tr class="item-row">
                <td><strong>${productName}</strong></td>
                <td>
                    <div class="item-detail">
                        <span>${dimensionsText}</span>
                        <span class="item-surface">${itemValue.value} ${itemValue.unit}</span>
                        <span class="item-desc">${item.description}</span>
                        <span class="item-weight">Poids: ${(item.quantity * item.weight).toFixed(2)} kg (${item.quantity} × ${item.weight.toFixed(2)})</span>
                    </div>
                </td>
                <td>
                    <button class="delete-item-btn" onclick="removeItem('${pkg.id}', ${index})" title="Supprimer cet article">×</button>
                </td>
            </tr>
        `;
    });
    
    // Ligne d'ajout avec ID normalisé
    const normalizedPkgId = normalizePackageId(pkg.id);
    
    if (pkg.isFree) {
        html += `
            <tr class="add-row">
                <td>
                    <input type="text" class="form-select" id="custom-name-${normalizedPkgId}" placeholder="Nom de l'article" style="width: 100%;">
                </td>
                <td>
                    <div class="free-form-row">
                        <input type="number" class="qty-input" id="qty-input-${normalizedPkgId}" placeholder="Qté" min="1" style="width: 50px;">
                        <span>×</span>
                        <input type="number" class="qty-input" id="length-input-${normalizedPkgId}" placeholder="L" min="1" style="width: 60px;">
                        <span>×</span>
                        <input type="number" class="qty-input" id="width-input-${normalizedPkgId}" placeholder="l" min="1" style="width: 60px;">
                        <input type="text" class="form-select" id="desc-input-${normalizedPkgId}" placeholder="Description" style="width: 80px;">
                        <input type="number" class="qty-input" id="weight-input-${normalizedPkgId}" placeholder="Poids/u" step="0.1" style="width: 60px;">
                    </div>
                </td>
                <td>
                    <button class="add-btn" onclick="addFreeItem('${normalizedPkgId}')">+</button>
                </td>
            </tr>
        `;
    } else if (hasAvailableProducts()) {
        html += `
            <tr class="add-row">
                <td>
                    <select class="form-select" id="product-select-${normalizedPkgId}" onchange="handleProductChange('${normalizedPkgId}', this.value)">
                        <option value="">Choisir produit...</option>
                        ${getAvailableProductsOptions()}
                    </select>
                </td>
                <td>
                    <select class="form-select" id="detail-select-${normalizedPkgId}" disabled onchange="handleDetailChange('${normalizedPkgId}', this.value)">
                        <option value="">Choisir détail...</option>
                    </select>
                </td>
                <td>
                    <input type="number" class="qty-input" id="qty-input-${normalizedPkgId}" placeholder="Qté" min="1" disabled 
                           oninput="validateQuantity(this)">
                    <button class="add-btn" onclick="addItem('${normalizedPkgId}')">+</button>
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
    console.log('✅ Éditeur de colis rendu avec succès pour le colis #' + pkg.id);
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
        id: normalizePackageId(colisageApp.nextPackageId++),
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
        id: normalizePackageId(colisageApp.nextPackageId++),
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
 * CORRECTION MAJEURE : Sélectionner un colis avec gestion d'erreur améliorée
 */
function selectPackage(packageId) {
    console.log('🎯 Tentative de sélection du colis:', packageId, '(type:', typeof packageId, ')');
    
    // Trouver le colis avec la fonction corrigée
    const pkg = findPackageById(packageId);
    
    if (!pkg) {
        console.error('❌ Impossible de sélectionner le colis - Colis non trouvé');
        
        // Afficher un message d'erreur à l'utilisateur
        showMessage(
            `❌ Erreur: Impossible de sélectionner le colis #${packageId}. Rechargement de la page...`,
            'error',
            3000
        );
        
        // Recharger la page après 3 secondes
        setTimeout(() => {
            console.log('🔄 Rechargement de la page suite à l\'erreur de sélection');
            window.location.reload();
        }, 3000);
        
        return;
    }
    
    // Normaliser l'ID sélectionné
    colisageApp.selectedPackageId = normalizePackageId(pkg.id);
    
    console.log('✅ Colis sélectionné avec succès:', pkg.id);
    console.log('📝 Nombre d\'articles dans ce colis:', pkg.items ? pkg.items.length : 0);
    
    // Forcer le re-rendu de l'interface
    render();
    
    // Vérifier que l'éditeur s'est bien affiché
    setTimeout(() => {
        const editorTitle = document.querySelector('.colis-editor-title');
        if (editorTitle && editorTitle.textContent.includes('#' + pkg.id)) {
            console.log('✅ Éditeur de colis affiché correctement');
        } else {
            console.warn('⚠️ L\'éditeur ne semble pas s\'être affiché correctement');
            
            // Tentative de correction automatique
            console.log('🔄 Tentative de correction automatique...');
            render();
        }
    }, 100);
}

/**
 * CORRECTION : Suppression de colis avec ID normalisé
 */
function handleDeletePackage(event, packageId) {
    if (event) {
        event.stopPropagation();
        event.preventDefault();
    }
    
    const normalizedId = normalizePackageId(packageId);
    console.log('🗑️ Suppression colis ID:', normalizedId, '(original:', packageId, ')');
    
    if (!confirm('Êtes-vous sûr de vouloir supprimer ce colis ?')) {
        return;
    }
    
    const originalLength = colisageApp.packages.length;
    colisageApp.packages = colisageApp.packages.filter(p => normalizePackageId(p.id) !== normalizedId);
    
    console.log(`✅ Colis supprimé. Avant: ${originalLength}, Après: ${colisageApp.packages.length}`);
    
    // Gérer la sélection après suppression
    if (normalizePackageId(colisageApp.selectedPackageId) === normalizedId) {
        colisageApp.selectedPackageId = colisageApp.packages.length > 0 ? colisageApp.packages[0].id : null;
        console.log('🎯 Nouveau colis sélectionné:', colisageApp.selectedPackageId);
    }
    
    console.log('💾 Sauvegarde automatique après suppression...');
    
    render();
    saveColisageAfterDelete(normalizedId);
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
    
    const savingMessage = showMessage('<span class="loading-spinner"></span> Sauvegarde de la suppression...', 'info', 0);
    
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
        if (savingMessage && savingMessage.parentNode) {
            savingMessage.parentNode.removeChild(savingMessage);
        }
        
        if (data.success) {
            console.log('✅ Suppression sauvegardée avec succès');
            showMessage('✅ Colis supprimé et sauvegardé !', 'success', 3000);
        } else {
            console.error('❌ Erreur lors de la sauvegarde de la suppression:', data.error);
            showMessage('❌ Erreur lors de la sauvegarde: ' + data.error + '<br><small>Rechargement automatique dans 3 secondes...</small>', 'error', 0);
            setTimeout(() => {
                window.location.reload();
            }, 3000);
        }
    })
    .catch(error => {
        if (savingMessage && savingMessage.parentNode) {
            savingMessage.parentNode.removeChild(savingMessage);
        }
        
        console.error('❌ Erreur de communication lors de la suppression:', error);
        showMessage('❌ Erreur de communication - Rechargement automatique dans 3 secondes...', 'error', 0);
        setTimeout(() => {
            window.location.reload();
        }, 3000);
    });
}

/**
 * Mettre à jour le multiplicateur d'un colis
 */
function updateMultiplier(packageId, value) {
    const pkg = findPackageById(packageId);
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
 * Gérer le changement de produit dans le formulaire d'ajout - CORRECTION avec affichage intelligent
 */
function handleProductChange(packageId, productId) {
    const normalizedId = normalizePackageId(packageId);
    const detailSelect = document.getElementById(`detail-select-${normalizedId}`);
    const qtyInput = document.getElementById(`qty-input-${normalizedId}`);
    const pkg = findPackageById(packageId);
    
    if (!detailSelect || !qtyInput || !pkg) {
        console.error('❌ Éléments manquants pour le changement de produit:', {
            detailSelect: !!detailSelect,
            qtyInput: !!qtyInput,
            pkg: !!pkg
        });
        return;
    }
    
    detailSelect.innerHTML = '<option value="">Choisir détail...</option>';
    detailSelect.disabled = !productId;
    
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
                
                // CORRECTION : Affichage intelligent des dimensions dans les options
                const { dimensionsText, valueText, unitText } = calculateDimensionsAndUnit(detail);
                
                if (pkg.multiplier > 1) {
                    option.textContent = `${maxPerColis} max par colis (${available} total) - ${dimensionsText} (${detail.description || 'N/A'})`;
                } else {
                    option.textContent = `${available} restants sur ${detail.pieces} - ${dimensionsText} (${detail.description || 'N/A'})`;
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
    const normalizedId = normalizePackageId(packageId);
    const detailSelect = document.getElementById(`detail-select-${normalizedId}`);
    const qtyInput = document.getElementById(`qty-input-${normalizedId}`);
    
    if (!detailSelect || !qtyInput) {
        console.error('❌ Éléments manquants pour le changement de détail');
        return;
    }
    
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
 * CORRECTION : Calcul du poids total d'un colis
 */
function calculatePackageWeight(pkg) {
    // Poids total = somme des (quantité × poids unitaire) de chaque ligne
    return pkg.items.reduce((sum, item) => {
        const lineWeight = item.quantity * item.weight;
        return sum + lineWeight;
    }, 0);
}

/**
 * Ajouter un article standard à un colis - VERSION SIMPLIFIÉE CORRIGÉE
 */
function addItem(packageId) {
    const normalizedId = normalizePackageId(packageId);
    const productSelect = document.getElementById(`product-select-${normalizedId}`);
    const detailSelect = document.getElementById(`detail-select-${normalizedId}`);
    const qtyInput = document.getElementById(`qty-input-${normalizedId}`);
    const pkg = findPackageById(packageId);

    if (!productSelect || !detailSelect || !qtyInput || !pkg) {
        console.error('❌ Éléments manquants pour l\'ajout d\'article');
        return;
    }

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
        // Augmenter la quantité de l'item existant
        existingItem.quantity += quantity;
    } else {
        // CALCUL DE POIDS SIMPLIFIÉ selon spécifications
        console.log('🔍 DEBUG POIDS SIMPLIFIÉ - Valeurs:', {
            productName: product.name,
            productWeight: product.weight,
            productWeightUnits: product.weight_units,
            itemQuantity: quantity
        });
        
        // Conversion des unités si nécessaire
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
            console.warn('⚠️ Unité de poids non gérée:', product.weight_units);
            productWeightInKg = product.weight;
        }
        
        // CORRECTION AUTOMATIQUE : Si poids > 100 et unité = kg, considérer comme grammes
        if ((product.weight_units === 0 || product.weight_units === undefined) && product.weight > 100) {
            console.warn('🚨 Auto-correction: Poids élevé avec unité kg - Conversion en grammes');
            productWeightInKg = product.weight / 1000;
            console.log('✅ Poids corrigé:', product.weight, 'supposés grammes →', productWeightInKg, 'kg');
        }
        
        // NOUVEAU CALCUL SIMPLIFIÉ : Poids unitaire du produit (weight est stocké dans item)
        console.log('⚖️ CALCUL POIDS SIMPLIFIÉ:', {
            productWeightInKg: productWeightInKg + ' kg',
            quantite: quantity,
            poidsLigne: (productWeightInKg * quantity).toFixed(3) + ' kg',
            formule: `${productWeightInKg} kg × ${quantity} pièces = ${(productWeightInKg * quantity).toFixed(3)} kg`
        });
        
        // Stocker le poids UNITAIRE dans l'item (pas le poids total)
        pkg.items.push({
            productId: productId,
            detailId: detailId,
            quantity: quantity,
            longueur: detail.longueur,
            largeur: detail.largeur,
            description: detail.description || '',
            weight: productWeightInKg, // CORRECTION : Poids UNITAIRE du produit
            customName: null
        });
    }

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
 * Ajouter un article libre à un colis - VERSION CORRIGÉE
 */
function addFreeItem(packageId) {
    const normalizedId = normalizePackageId(packageId);
    const customNameInput = document.getElementById(`custom-name-${normalizedId}`);
    const qtyInput = document.getElementById(`qty-input-${normalizedId}`);
    const lengthInput = document.getElementById(`length-input-${normalizedId}`);
    const widthInput = document.getElementById(`width-input-${normalizedId}`);
    const descInput = document.getElementById(`desc-input-${normalizedId}`);
    const weightInput = document.getElementById(`weight-input-${normalizedId}`);
    
    const pkg = findPackageById(packageId);

    if (!customNameInput || !qtyInput || !lengthInput || !widthInput || !descInput || !weightInput || !pkg) {
        console.error('❌ Éléments manquants pour l\'ajout d\'article libre');
        return;
    }

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
    const pkg = findPackageById(packageId);
    if (!pkg) {
        console.error('❌ Colis non trouvé pour la suppression d\'article');
        return;
    }
    
    pkg.items.splice(itemIndex, 1);
    
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
 * Afficher un message à l'utilisateur
 */
function showMessage(message, type = 'info', duration = 5000) {
    const existingMessages = document.querySelectorAll(`.colisage-${type}`);
    existingMessages.forEach(msg => {
        if (msg.parentNode) {
            msg.parentNode.removeChild(msg);
        }
    });
    
    const messageDiv = document.createElement('div');
    messageDiv.className = `colisage-${type}`;
    messageDiv.innerHTML = message;
    
    const container = document.querySelector('.colisage-container');
    if (container) {
        container.insertBefore(messageDiv, container.firstChild);
        
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

/**
 * CORRECTION : Fonction de test pour diagnostiquer les problèmes d'édition
 */
window.debugColisageSelection = function() {
    console.group('🔍 Diagnostic de sélection des colis');
    
    console.log('📋 Colis disponibles:');
    colisageApp.packages.forEach((pkg, index) => {
        console.log(`  ${index + 1}. ID: ${pkg.id} (type: ${typeof pkg.id}) - Articles: ${pkg.items.length}`);
    });
    
    console.log(`🎯 Colis actuellement sélectionné: ${colisageApp.selectedPackageId} (type: ${typeof colisageApp.selectedPackageId})`);
    
    if (colisageApp.selectedPackageId) {
        const foundPkg = findPackageById(colisageApp.selectedPackageId);
        console.log(`🔍 Résultat de la recherche:`, foundPkg ? '✅ Trouvé' : '❌ Non trouvé');
        
        if (foundPkg) {
            console.log(`📝 Détails du colis trouvé:`, {
                id: foundPkg.id,
                type: typeof foundPkg.id,
                items: foundPkg.items.length,
                isFree: foundPkg.isFree
            });
        }
    }
    
    // Vérifier l'éditeur
    const editorContainer = document.getElementById('colis-editor');
    console.log(`🖥️ Conteneur éditeur:`, editorContainer ? '✅ Présent' : '❌ Absent');
    
    if (editorContainer) {
        const hasEmptyEditor = editorContainer.querySelector('.empty-editor');
        const hasEditorTitle = editorContainer.querySelector('.colis-editor-title');
        console.log(`📝 État de l'éditeur:`, hasEmptyEditor ? '⚪ Vide' : (hasEditorTitle ? '✅ Actif' : '❓ Inconnu'));
    }
    
    console.groupEnd();
    
    // Test de sélection
    if (colisageApp.packages.length > 0) {
        const firstPackage = colisageApp.packages[0];
        console.log(`🧪 Test de sélection du premier colis: ${firstPackage.id}`);
        selectPackage(firstPackage.id);
    }
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

// Exposer la fonction de diagnostic
window.debugColisageSelection = debugColisageSelection;