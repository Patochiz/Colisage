/* Copyright (C) 2025 Patrice GOURMELEN <pgourmelen@diamant-industrie.com>
 *
 * VERSION AMÉLIORÉE : Notifications flottantes + Numérotation séquentielle + Édition titres
 * MODIFICATION : Utilise le service ID=361 pour les titres au lieu de product_type=9
 * 
 * Nouvelles fonctionnalités :
 * - Auto-sauvegarde à chaque ajout d'article
 * - Boutons de sélection rapide dans le récapitulatif
 * - Boutons de quantité rapide (x4, x6, x8, Max)
 * - Messages de notification flottants en position fixed
 * - Numérotation séquentielle 1, 2, 3... (indépendante des rowid en base)
 * - Édition des titres de cartes (Service ID=361)
 */

/**
 * JavaScript pour le module Colisage - Version complète avec édition titres
 */

// Variables globales
let colisageApp = {
    productData: {},
    packages: [],
    nextPackageId: 1,
    selectedPackageId: null,
    commandeId: null,
    token: null,
    urlBase: '',
    isSaving: false // Éviter les sauvegardes multiples simultanées
};

/**
 * CORRECTION : Fonction utilitaire pour vérifier les valeurs nulles/vides
 */
function isValidDimension(value) {
    return value !== null && 
           value !== undefined && 
           value !== '' && 
           value !== 'null' && 
           value !== '0' &&
           parseFloat(value) > 0;
}

/**
 * CORRECTION URGENTE : Fonctions utilitaires pour éviter l'erreur toFixed
 */
function safeToFixed(value, decimals = 2) {
    const num = parseFloat(value);
    return isNaN(num) ? '0.00' : num.toFixed(decimals);
}

function safeWeightCalculation(quantity, weight) {
    const qty = parseFloat(quantity) || 0;
    const w = parseFloat(weight) || 0;
    return qty * w;
}

/**
 * FONCTION CORRIGÉE : Conversion robuste des poids vers kg utilisant le système scale de Dolibarr
 */
function convertWeightToKg(weight, weightUnits) {
    const numWeight = parseFloat(weight) || 0;
    const units = parseInt(weightUnits);
    
    if (numWeight === 0 || !units) {
        return 0.0;
    }
    
    let scale;
    switch (units) {
        case 1: // T (tonne) - scale = 6 selon votre config
            scale = 6;
            break;
        case 2: // KG (kilogramme) - scale = 3 selon votre config  
            scale = 3;
            break;
        case 3: // G (gramme) - scale = 0 (estimation logique)
            scale = 0;
            break;
        case 4: // MG (milligramme) - scale = -3 selon votre config
            scale = -3;
            break;
        default:
            console.warn('⚠️ Unité de poids non reconnue:', units, '- Considéré comme kg (scale=3)');
            scale = 3;
            break;
    }
    
    const conversion_factor = Math.pow(10, scale - 3);
    const result = numWeight * conversion_factor;
    
    if (window.colisageData && window.colisageData.debugMode) {
        console.log(`🔄 Conversion JS: ${numWeight} (unité ${units}, scale ${scale}) = ${result} kg`);
    }
    
    return result;
}

/**
 * FONCTION CORRIGÉE : Obtenir le nom de l'unité de poids selon votre configuration
 */
function getWeightUnitName(weightUnits) {
    const units = parseInt(weightUnits);
    
    switch (units) {
        case 1: return 'T';
        case 2: return 'KG';
        case 3: return 'G';
        case 4: return 'MG';
        default: return 'KG (?)';
    }
}

/**
 * NOUVEAU : Fonction de normalisation des IDs (toujours en string)
 */
function normalizePackageId(id) {
    return String(id);
}

/**
 * NOUVEAU : Trouver un colis par ID
 */
function findPackageById(packageId) {
    const normalizedId = normalizePackageId(packageId);
    const pkg = colisageApp.packages.find(p => normalizePackageId(p.id) === normalizedId);
    
    if (pkg) {
        console.log(`✅ Colis trouvé: ID ${pkg.id}`);
    } else {
        console.error(`❌ Colis non trouvé: ID ${packageId}`, {
            searchedId: packageId,
            availableIds: colisageApp.packages.map(p => p.id)
        });
    }
    
    return pkg;
}

/**
 * NOUVEAU : Réindexer tous les colis pour avoir une numérotation séquentielle
 * Cette fonction attribue les IDs 1, 2, 3... à tous les colis
 */
function reindexPackages() {
    colisageApp.packages.forEach((pkg, index) => {
        const newId = index + 1;
        const oldId = pkg.id;
        
        // Mettre à jour l'ID du colis
        pkg.id = normalizePackageId(newId);
        
        // Si c'est le colis sélectionné, mettre à jour la sélection
        if (normalizePackageId(colisageApp.selectedPackageId) === normalizePackageId(oldId)) {
            colisageApp.selectedPackageId = pkg.id;
            console.log(`🎯 Colis sélectionné mis à jour: ${oldId} → ${pkg.id}`);
        }
    });
    
    // Le prochain ID sera toujours le nombre de colis + 1
    colisageApp.nextPackageId = colisageApp.packages.length + 1;
    
    console.log(`🔄 Colis réindexés: ${colisageApp.packages.map(p => p.id).join(', ')}`);
    console.log(`🔢 Prochain ID: ${colisageApp.nextPackageId}`);
}

// Initialisation quand le DOM est prêt
document.addEventListener('DOMContentLoaded', function() {
    if (typeof window.colisageData !== 'undefined') {
        colisageApp.productData = window.colisageData.productData || {};
        colisageApp.commandeId = window.colisageData.commandeId;
        colisageApp.token = window.colisageData.token;
        colisageApp.urlBase = window.colisageData.urlBase;
        
        console.log('Colisage App initialisée:', colisageApp);
        
        // Créer le container pour les notifications flottantes
        createNotificationsContainer();
        
        loadExistingPackages();
        render();
        
        // Le bouton "Enregistrer" n'est plus nécessaire car auto-save
        // Mais on le laisse pour les utilisateurs qui veulent sauvegarder manuellement
        const saveBtn = document.getElementById('save-btn');
        if (saveBtn) {
            saveBtn.addEventListener('click', saveColisage);
        }
    } else {
        console.error('Données Colisage non trouvées');
    }
});

/**
 * NOUVEAU : Créer le container pour les notifications flottantes
 */
function createNotificationsContainer() {
    // Vérifier si le container existe déjà
    let container = document.querySelector('.colisage-notifications-container');
    
    if (!container) {
        container = document.createElement('div');
        container.className = 'colisage-notifications-container';
        document.body.appendChild(container);
        console.log('✅ Container de notifications flottantes créé');
    }
    
    return container;
}

/**
 * MODIFIÉ : Chargement des colis existants avec réindexation automatique
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
            // Charger les colis (avec leurs rowid de base de données)
            colisageApp.packages = data.data.packages;
            
            console.log('📦 Colis chargés depuis la base:', colisageApp.packages.length);
            
            // NOUVEAU : Réindexer pour avoir des numéros séquentiels 1, 2, 3...
            reindexPackages();
            
            console.log('✅ Colis existants chargés et réindexés');
        } else {
            console.log('ℹ️ Aucun colis existant trouvé');
            colisageApp.packages = [];
            colisageApp.nextPackageId = 1;
        }
        
        render();
    })
    .catch(error => {
        console.error('❌ Erreur lors du chargement:', error);
        colisageApp.packages = [];
        colisageApp.nextPackageId = 1;
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
 * AMÉLIORATION : Rendu de la section récapitulatif des produits avec titres de sections
 */
function renderProductsSummary() {
    const container = document.getElementById('products-summary');
    if (!container) return;

    let html = '';
    
    // NOUVEAU : Afficher les produits avant le premier titre (s'il y en a)
    if (window.colisageData.produitsAvantPremierTitre && 
        window.colisageData.produitsAvantPremierTitre.length > 0) {
        
        html += renderProductsGroup(window.colisageData.produitsAvantPremierTitre, null, null, null);
    }
    
    // NOUVEAU : Afficher les sections avec leurs produits
    if (window.colisageData.sections && window.colisageData.sections.length > 0) {
        window.colisageData.sections.forEach((section, sectionIndex) => {
            html += renderProductsGroup(section.produits, section.titre, section.description, sectionIndex);
        });
    } else {
        // Fallback : afficher tous les produits sans section (ancien comportement)
        const allProductIds = Object.keys(colisageApp.productData);
        html += renderProductsGroup(allProductIds, null, null, null);
    }

    container.innerHTML = html;
}

/**
 * FONCTION COMPLÈTE AVEC ÉDITION DES TITRES : Afficher un groupe de produits
 */
function renderProductsGroup(productIds, titre = null, description = null, sectionIndex = null) {
    let html = '';
    
    // Afficher le titre de section si présent AVEC bouton d'édition
    if (titre !== null && sectionIndex !== null) {
        const section = window.colisageData.sections[sectionIndex];
        const rowid = section ? section.rowid : null;
        
        html += `
            <div class="section-titre" data-section="${sectionIndex}" data-rowid="${rowid}">
                <div class="section-titre-icon">📋</div>
                <div class="section-titre-content">
                    <div class="section-titre-text" id="section-titre-text-${sectionIndex}">${titre}</div>
                    <div class="section-titre-actions">
                        <button class="section-titre-edit-btn" onclick="startEditCardTitle(${sectionIndex})" title="Modifier le titre">
                            ✏️
                        </button>
                    </div>
                </div>
            </div>
        `;
    }
    
    // Afficher les produits de cette section
    productIds.forEach(productId => {
        const product = colisageApp.productData[productId];
        if (!product) return;
        
        html += `<div class="product-group ${titre ? 'product-in-section' : ''}">`;
        html += `<div class="product-group-title">${product.name}</div>`;
        
        product.details.forEach((detail, index) => {
            const used = getUsedQuantity(productId, index);
            const total = detail.pieces;
            const available = getAvailableQuantity(productId, index);
            const percentage = total > 0 ? (used / total) * 100 : 0;
            
            // CORRECTION : Ne pas afficher 1000x1000 pour les produits standards
            let dimensionsText = '';
            const hasRealWidth = detail.largeur && detail.largeur !== null && detail.largeur > 0 && detail.largeur !== 1000;
            const hasRealLength = detail.longueur && detail.longueur !== null && detail.longueur > 0 && detail.longueur !== 1000;
            
            if (hasRealWidth && hasRealLength) {
                dimensionsText = `${detail.longueur} × ${detail.largeur}`;
            } else if (hasRealLength && !hasRealWidth) {
                dimensionsText = `${detail.longueur}`;
            } else {
                dimensionsText = 'Standard';
            }
            
            let valueText = '';
            let unitText = 'u';
            
            let productWeightInKg = convertWeightToKg(product.weight, product.weight_units);
            
            if (product.weight_units === 3 && product.weight > 1) {
                console.warn('⚠️ Détection (résumé): Poids', product.weight, 'avec unité "grammes" mais semble déjà être en kg/m²');
                productWeightInKg = product.weight;
            }
            
            // Vérifier si ce sont de vraies dimensions (pas 1000 par défaut)
            const hasValidWidth = isValidDimension(detail.largeur) && detail.largeur !== 1000;
            const hasValidLength = isValidDimension(detail.longueur) && detail.longueur !== 1000;
            
            if (hasValidLength && hasValidWidth) {
                if (detail.total_value && detail.total_value > 0) {
                    valueText = detail.total_value.toFixed(2);
                } else {
                    valueText = (detail.longueur * detail.largeur / 1000000).toFixed(2);
                }
                unitText = 'm²';
            } else if (hasValidLength && !hasValidWidth) {
                if (detail.total_value && detail.total_value > 0) {
                    valueText = detail.total_value.toFixed(2);
                } else {
                    valueText = (detail.longueur / 1000).toFixed(2);
                }
                unitText = 'ml';
            } else {
                if (detail.total_value && detail.total_value > 0) {
                    valueText = detail.total_value.toString();
                } else {
                    valueText = detail.pieces.toString();
                }
                unitText = 'u';
            }
            
            let badgeClass = 'pieces-badge';
            if (unitText === 'm²') {
                badgeClass = 'surface-badge';
            } else if (unitText === 'ml') {
                badgeClass = 'length-badge';
            }
            
            // Bouton de sélection rapide
            const quickSelectBtn = available > 0 && colisageApp.selectedPackageId && !findPackageById(colisageApp.selectedPackageId)?.isFree
                ? `<button class="quick-select-btn" onclick="quickSelectDetail('${productId}', ${index})" title="Sélectionner ce détail">➕</button>`
                : '';
            
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
                        ${quickSelectBtn}
                    </div>
                </div>
            `;
        });
        
        html += `</div>`;
    });
    
    return html;
}

/**
 * ✏️ ÉDITION DES TITRES - Démarrer l'édition d'un titre de carte
 */
window.startEditCardTitle = function(sectionIndex) {
    console.log('✏️ Démarrage édition titre section', sectionIndex);
    
    const section = window.colisageData.sections[sectionIndex];
    if (!section) {
        console.error('❌ Section introuvable:', sectionIndex);
        return;
    }
    
    const rowid = section.rowid;
    if (!rowid) {
        showMessage('❌ Impossible de modifier ce titre (ID manquant)', 'error', 3000);
        return;
    }
    
    const textElement = document.getElementById(`section-titre-text-${sectionIndex}`);
    const actionsElement = textElement.nextElementSibling;
    
    if (!textElement || !actionsElement) {
        console.error('❌ Éléments DOM introuvables');
        return;
    }
    
    const currentTitle = textElement.textContent.trim();
    
    // Remplacer le texte par un input
    textElement.innerHTML = `<input type="text" class="section-titre-edit-input" id="section-titre-edit-input-${sectionIndex}" value="${currentTitle.replace(/"/g, '&quot;')}">`;
    
    // Remplacer les boutons d'action
    actionsElement.innerHTML = `
        <button class="section-titre-save-btn" onclick="saveCardTitle(${sectionIndex})" title="Sauvegarder">
            ✓
        </button>
        <button class="section-titre-cancel-btn" onclick="cancelEditCardTitle(${sectionIndex}, \`${currentTitle.replace(/`/g, '\\`').replace(/\\/g, '\\\\')}\`)" title="Annuler">
            ✗
        </button>
    `;
    
    // Focus sur l'input
    const inputElement = document.getElementById(`section-titre-edit-input-${sectionIndex}`);
    if (inputElement) {
        inputElement.focus();
        inputElement.select();
        
        // Sauvegarder sur Enter
        inputElement.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                saveCardTitle(sectionIndex);
            } else if (e.key === 'Escape') {
                e.preventDefault();
                cancelEditCardTitle(sectionIndex, currentTitle);
            }
        });
    }
};

/**
 * ✏️ ÉDITION DES TITRES - Sauvegarder le nouveau titre
 */
window.saveCardTitle = function(sectionIndex) {
    console.log('💾 Sauvegarde titre section', sectionIndex);
    
    const section = window.colisageData.sections[sectionIndex];
    if (!section) {
        console.error('❌ Section introuvable:', sectionIndex);
        showMessage('❌ Erreur: Section introuvable', 'error', 3000);
        return;
    }
    
    const inputElement = document.getElementById(`section-titre-edit-input-${sectionIndex}`);
    if (!inputElement) {
        console.error('❌ Input introuvable');
        showMessage('❌ Erreur: Champ d\'édition introuvable', 'error', 3000);
        return;
    }
    
    const newTitle = inputElement.value.trim();
    
    if (!newTitle) {
        showMessage('⚠️ Le titre ne peut pas être vide', 'error', 3000);
        inputElement.focus();
        return;
    }
    
    console.log(`📝 Sauvegarde du titre pour la section ${sectionIndex}:`, {
        rowid: section.rowid,
        ancienTitre: section.titre,
        nouveauTitre: newTitle
    });
    
    // Afficher un message de sauvegarde
    const savingMessage = showMessage('<span class="loading-spinner"></span> Sauvegarde du titre...', 'info', 0);
    
    // Appel AJAX pour sauvegarder
    const formData = new FormData();
    formData.append('action', 'save_card_title');
    formData.append('token', colisageApp.token);
    formData.append('rowid', section.rowid);
    formData.append('new_title', newTitle);
    
    fetch(colisageApp.urlBase + '/ajax/save_card_title.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (savingMessage && savingMessage.remove) {
            savingMessage.remove();
        }
        
        if (data.success) {
            console.log('✅ Titre sauvegardé avec succès');
            
            // Mettre à jour les données locales
            section.titre = newTitle;
            section.ref_chantier = newTitle;
            
            // Re-rendre le récapitulatif
            renderProductsSummary();
            
            showMessage('✅ Titre sauvegardé !', 'success', 3000);
        } else {
            console.error('❌ Erreur lors de la sauvegarde:', data.error);
            showMessage('❌ Erreur: ' + data.error, 'error', 5000);
            
            // Restaurer l'interface d'édition en cas d'erreur
            renderProductsSummary();
        }
    })
    .catch(error => {
        if (savingMessage && savingMessage.remove) {
            savingMessage.remove();
        }
        
        console.error('❌ Erreur de communication:', error);
        showMessage('❌ Erreur de communication avec le serveur', 'error', 5000);
        
        // Restaurer l'interface d'édition
        renderProductsSummary();
    });
};

/**
 * ✏️ ÉDITION DES TITRES - Annuler l'édition
 */
window.cancelEditCardTitle = function(sectionIndex, originalTitle) {
    console.log('✗ Annulation édition titre section', sectionIndex);
    
    // Re-rendre le récapitulatif pour restaurer l'état initial
    renderProductsSummary();
};

/**
 * Mettre à jour l'état des boutons d'actions principales
 */
function updateMainActionButtons() {
    const btnNewColis = document.getElementById('btn-new-colis');
    const btnFreeColis = document.getElementById('btn-free-colis');
    
    if (btnNewColis) {
        const hasProducts = hasAvailableProducts();
        btnNewColis.disabled = !hasProducts;
        
        if (hasProducts) {
            btnNewColis.classList.remove('btn-disabled');
            btnNewColis.title = 'Créer un nouveau colis standard';
        } else {
            btnNewColis.classList.add('btn-disabled');
            btnNewColis.title = 'Plus de produits disponibles';
        }
    }
    
    if (btnFreeColis) {
        btnFreeColis.disabled = false;
        btnFreeColis.title = 'Créer un nouveau colis libre';
    }
}

function renderColisSummary() {
    const container = document.getElementById('colis-summary');
    if (!container) return;

    const hasProducts = hasAvailableProducts();
    const remainingPieces = getTotalRemainingPieces();
    
    console.log('🎨 Rendu des colis. IDs:', colisageApp.packages.map(p => p.id).join(', '));
    console.log('🎯 Colis sélectionné:', colisageApp.selectedPackageId);
    
    if (colisageApp.packages.length === 0) {
        container.innerHTML = `
            <div style="text-align: center; padding: 2rem 1rem; color: #6c757d; font-style: italic;">
                ${hasProducts ? 'Aucun colis créé' : `Stock épuisé - ${remainingPieces} pièces restantes`}
            </div>
        `;
        return;
    }

    let html = '';
    
    if (!hasProducts) {
        html += `
            <div style="background: #fff3cd; color: #856404; padding: 0.5rem; border-radius: 4px; margin-bottom: 1rem; font-size: 0.85rem; text-align: center;">
                ⚠️ Tous les produits ont été colisés (${remainingPieces} pièces restantes)
            </div>
        `;
    }
    
    html += '<div class="colis-list">';
    
    colisageApp.packages.forEach((pkg, index) => {
        const weightPerPackage = pkg.items.reduce((sum, item) => sum + (item.quantity * item.weight), 0);
        const surfacePerPackage = pkg.items.reduce((sum, item) => {
            if (item.largeur && item.largeur !== null && item.largeur > 0) {
                return sum + (item.quantity * item.longueur * item.largeur / 1000000);
            }
            return sum;
        }, 0);
        
        const isSelected = normalizePackageId(colisageApp.selectedPackageId) === normalizePackageId(pkg.id);
        
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
                            <span>Poids: <strong>${weightPerPackage.toFixed(1)} kg</strong>/colis</span>
                            <span>Surface: <strong>${surfacePerPackage.toFixed(2)} m²</strong>/colis</span>
                            <span>Total: <strong>${(weightPerPackage * pkg.multiplier).toFixed(1)} kg</strong></span>
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
                
                let itemDisplay = '';
                let surfaceDisplay = '0.00 m²';
                
                if (item.largeur && item.largeur !== null && item.largeur > 0) {
                    itemDisplay = `${item.quantity} × ${item.longueur}×${item.largeur}`;
                    surfaceDisplay = `${(item.quantity * item.longueur * item.largeur / 1000000).toFixed(2)} m²`;
                } else if (item.longueur && item.longueur !== null && item.longueur > 0) {
                    itemDisplay = `${item.quantity} × ${item.longueur}`;
                    surfaceDisplay = `${(item.quantity * item.longueur / 1000).toFixed(2)} ml`;
                } else {
                    itemDisplay = `${item.quantity} unités`;
                    surfaceDisplay = `${item.quantity} u`;
                }
                
                html += `
                    <div class="colis-detail-item">
                        <div class="colis-detail-left">
                            <strong>${productName}</strong>
                        </div>
                        <div class="colis-detail-right">
                            <span>${itemDisplay}</span>
                            <span class="colis-detail-surface">${surfaceDisplay}</span>
                            <span class="colis-detail-desc">${item.description}</span>
                        </div>
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
 * AMÉLIORATION 3 : Rendu de l'éditeur de colis avec boutons de quantité rapide
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

    const pkg = findPackageById(colisageApp.selectedPackageId);
    
    if (!pkg) {
        console.error('❌ Erreur critique: Colis sélectionné non trouvé lors du rendu');
        
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

    const weightPerPackage = pkg.items.reduce((sum, item) => sum + (item.quantity * item.weight), 0);
    const surfacePerPackage = pkg.items.reduce((sum, item) => {
        if (item.largeur && item.largeur !== null && item.largeur > 0) {
            return sum + (item.quantity * item.longueur * item.largeur / 1000000);
        }
        return sum;
    }, 0);
    
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
                <div>Poids/colis: <strong>${weightPerPackage.toFixed(1)} kg</strong></div>
                <div>Surface/colis: <strong>${surfacePerPackage.toFixed(2)} m²</strong></div>
                <div>Total ${pkg.multiplier} colis: <strong>${(weightPerPackage * pkg.multiplier).toFixed(1)} kg</strong> | <strong>${(surfacePerPackage * pkg.multiplier).toFixed(2)} m²</strong></div>
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
    
    pkg.items.forEach((item, index) => {
        const productName = item.productId.startsWith('prod_') 
            ? colisageApp.productData[item.productId]?.name || 'Produit inconnu'
            : item.customName || 'Article libre';
        
        let itemDisplay = '';
        let surfaceDisplay = '0.00 m²';
        
        if (item.largeur && item.largeur !== null && item.largeur > 0) {
            itemDisplay = `${item.quantity} × ${item.longueur}×${item.largeur}`;
            surfaceDisplay = `${(item.quantity * item.longueur * item.largeur / 1000000).toFixed(2)} m²`;
        } else if (item.longueur && item.longueur !== null && item.longueur > 0) {
            itemDisplay = `${item.quantity} × ${item.longueur}`;
            surfaceDisplay = `${(item.quantity * item.longueur / 1000).toFixed(2)} ml`;
        } else {
            itemDisplay = `${item.quantity} unités`;
            surfaceDisplay = `${item.quantity} u`;
        }
        
        html += `
            <tr class="item-row">
                <td><strong>${productName}</strong></td>
                <td>
                    <div class="item-detail">
                        <span>${itemDisplay}</span>
                        <span class="item-surface">${surfaceDisplay}</span>
                        <span class="item-desc">${item.description}</span>
                    </div>
                </td>
                <td>
                    <button class="delete-item-btn" onclick="removeItem('${pkg.id}', ${index})" title="Supprimer cet article">×</button>
                </td>
            </tr>
        `;
    });
    
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
                        <input type="number" class="qty-input" id="weight-input-${normalizedPkgId}" placeholder="kg/u" step="0.01" min="0" title="Poids unitaire en kg" style="width: 60px;">
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
                    <div style="display: flex; flex-direction: column; gap: 0.25rem; align-items: center;">
                        <input type="number" class="qty-input" id="qty-input-${normalizedPkgId}" placeholder="Qté" min="1" disabled 
                               oninput="validateQuantity(this)" style="width: 80px;">
                        
                        <!-- AMÉLIORATION 3 : Boutons de quantité rapide -->
                        <div class="quick-qty-buttons" id="quick-qty-${normalizedPkgId}" style="display: none;">
                            <button class="quick-qty-btn" onclick="setQuickQuantity('${normalizedPkgId}', 4)" title="Quantité: 4">×4</button>
                            <button class="quick-qty-btn" onclick="setQuickQuantity('${normalizedPkgId}', 6)" title="Quantité: 6">×6</button>
                            <button class="quick-qty-btn" onclick="setQuickQuantity('${normalizedPkgId}', 8)" title="Quantité: 8">×8</button>
                            <button class="quick-qty-btn quick-qty-max" onclick="setMaxQuantity('${normalizedPkgId}')" title="Quantité maximum">Max</button>
                        </div>
                        
                        <button class="add-btn" onclick="addItem('${normalizedPkgId}')">+</button>
                    </div>
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
    updateMainActionButtons();
}

/**
 * MODIFIÉ : Créer un nouveau colis standard avec ID séquentiel
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
 * MODIFIÉ : Créer un nouveau colis libre avec ID séquentiel
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
 * Sélectionner un colis
 */
function selectPackage(packageId) {
    console.log('🎯 Tentative de sélection du colis:', packageId);
    
    const pkg = findPackageById(packageId);
    
    if (!pkg) {
        console.error('❌ Impossible de sélectionner le colis - Colis non trouvé');
        
        showMessage(
            `❌ Erreur: Impossible de sélectionner le colis #${packageId}. Rechargement de la page...`,
            'error',
            3000
        );
        
        setTimeout(() => {
            console.log('🔄 Rechargement de la page suite à l\'erreur de sélection');
            window.location.reload();
        }, 3000);
        
        return;
    }
    
    colisageApp.selectedPackageId = normalizePackageId(pkg.id);
    
    console.log('✅ Colis sélectionné avec succès:', pkg.id);
    console.log('📝 Nombre d\'articles dans ce colis:', pkg.items ? pkg.items.length : 0);
    
    render();
    
    setTimeout(() => {
        const editorTitle = document.querySelector('.colis-editor-title');
        if (editorTitle && editorTitle.textContent.includes('#' + pkg.id)) {
            console.log('✅ Éditeur de colis affiché correctement');
        } else {
            console.warn('⚠️ L\'éditeur ne semble pas s\'être affiché correctement');
            console.log('🔄 Tentative de correction automatique...');
            render();
        }
    }, 100);
}

/**
 * MODIFIÉ : Suppression de colis avec réindexation automatique
 */
function handleDeletePackage(event, packageId) {
    if (event) {
        event.stopPropagation();
        event.preventDefault();
    }
    
    const normalizedId = normalizePackageId(packageId);
    console.log('🗑️ Suppression colis ID:', normalizedId);
    
    if (!confirm('Êtes-vous sûr de vouloir supprimer ce colis ?')) {
        return;
    }
    
    const originalLength = colisageApp.packages.length;
    colisageApp.packages = colisageApp.packages.filter(p => normalizePackageId(p.id) !== normalizedId);
    
    console.log(`✅ Colis supprimé. Avant: ${originalLength}, Après: ${colisageApp.packages.length}`);
    
    // Réindexer les colis restants pour avoir toujours 1, 2, 3...
    reindexPackages();
    
    // Si le colis supprimé était sélectionné, sélectionner le premier colis restant
    if (!findPackageById(colisageApp.selectedPackageId) && colisageApp.packages.length > 0) {
        colisageApp.selectedPackageId = colisageApp.packages[0].id;
        console.log('🎯 Nouveau colis sélectionné:', colisageApp.selectedPackageId);
    }
    
    console.log('💾 Sauvegarde automatique après suppression...');
    
    render();
    saveColisageAfterDelete(normalizedId);
}

/**
 * AMÉLIORATION 1 : Sauvegarder le colisage après suppression d'un colis
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
        if (savingMessage && savingMessage.remove) {
            savingMessage.remove();
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
        if (savingMessage && savingMessage.remove) {
            savingMessage.remove();
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
 * Gérer le changement de produit dans le formulaire d'ajout
 */
function handleProductChange(packageId, productId) {
    const normalizedId = normalizePackageId(packageId);
    const detailSelect = document.getElementById(`detail-select-${normalizedId}`);
    const qtyInput = document.getElementById(`qty-input-${normalizedId}`);
    const quickQtyDiv = document.getElementById(`quick-qty-${normalizedId}`);
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
    
    // Cacher les boutons de quantité rapide
    if (quickQtyDiv) {
        quickQtyDiv.style.display = 'none';
    }

    if (productId) {
        const product = colisageApp.productData[productId];
        product.details.forEach((detail, index) => {
            const available = getAvailableQuantity(productId, index);
            if (available > 0) {
                const maxPerColis = Math.floor(available / pkg.multiplier);
                const option = document.createElement('option');
                option.value = detail.detail_id;
                option.dataset.maxPerColis = maxPerColis;
                
                let dimensionText = '';
                if (detail.largeur && detail.largeur !== null && detail.largeur > 0) {
                    dimensionText = `${detail.longueur}×${detail.largeur}mm`;
                } else if (detail.longueur && detail.longueur !== null && detail.longueur > 0) {
                    dimensionText = `${detail.longueur}mm`;
                } else {
                    dimensionText = 'Standard';
                }
                
                if (pkg.multiplier > 1) {
                    option.textContent = `${maxPerColis} max par colis (${available} total) - ${dimensionText} (${detail.description || 'N/A'})`;
                } else {
                    option.textContent = `${available} restants sur ${detail.pieces} - ${dimensionText} (${detail.description || 'N/A'})`;
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
    const quickQtyDiv = document.getElementById(`quick-qty-${normalizedId}`);
    
    if (!detailSelect || !qtyInput) {
        console.error('❌ Éléments manquants pour le changement de détail');
        return;
    }
    
    if (!detailId) {
        qtyInput.value = '';
        qtyInput.max = '';
        qtyInput.disabled = true;
        
        // Cacher les boutons de quantité rapide
        if (quickQtyDiv) {
            quickQtyDiv.style.display = 'none';
        }
    } else {
        const selectedOption = detailSelect.options[detailSelect.selectedIndex];
        const maxPerColis = parseInt(selectedOption.dataset.maxPerColis);
        
        qtyInput.max = maxPerColis;
        qtyInput.disabled = false;
        qtyInput.placeholder = `Max: ${maxPerColis}`;
        
        // AMÉLIORATION 3 : Afficher les boutons de quantité rapide
        if (quickQtyDiv) {
            quickQtyDiv.style.display = 'flex';
        }
        
        if (parseInt(qtyInput.value) > maxPerColis) {
            qtyInput.value = '';
        }
        
        // Mettre le focus sur l'input de quantité
        qtyInput.focus();
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
 * Calcul du poids total d'un colis
 */
function calculatePackageWeight(pkg) {
    return pkg.items.reduce((sum, item) => {
        const lineWeight = safeWeightCalculation(item.quantity, item.weight);
        return sum + lineWeight;
    }, 0);
}

/**
 * AMÉLIORATION 2 : Sélection rapide d'un détail depuis le récapitulatif
 */
function quickSelectDetail(productId, detailIndex) {
    console.log('⚡ Sélection rapide:', productId, 'détail', detailIndex);
    
    if (!colisageApp.selectedPackageId) {
        showMessage('⚠️ Veuillez d\'abord sélectionner ou créer un colis', 'error', 3000);
        return;
    }
    
    const pkg = findPackageById(colisageApp.selectedPackageId);
    if (!pkg) {
        showMessage('❌ Erreur: colis introuvable', 'error', 3000);
        return;
    }
    
    if (pkg.isFree) {
        showMessage('⚠️ Cette fonction n\'est pas disponible pour les colis libres', 'error', 3000);
        return;
    }
    
    const normalizedId = normalizePackageId(colisageApp.selectedPackageId);
    const productSelect = document.getElementById(`product-select-${normalizedId}`);
    const detailSelect = document.getElementById(`detail-select-${normalizedId}`);
    const qtyInput = document.getElementById(`qty-input-${normalizedId}`);
    
    if (!productSelect || !detailSelect || !qtyInput) {
        console.error('❌ Éléments du formulaire introuvables');
        return;
    }
    
    // Sélectionner le produit
    productSelect.value = productId;
    
    // Déclencher le changement pour remplir le combo détail
    handleProductChange(normalizedId, productId);
    
    // Attendre que le combo détail soit rempli
    setTimeout(() => {
        const product = colisageApp.productData[productId];
        if (!product || !product.details[detailIndex]) {
            console.error('❌ Produit ou détail introuvable');
            return;
        }
        
        const detail = product.details[detailIndex];
        detailSelect.value = detail.detail_id;
        
        // Déclencher le changement pour activer le champ quantité
        handleDetailChange(normalizedId, detail.detail_id);
        
        // Feedback visuel
        showMessage('✅ Détail sélectionné ! Entrez la quantité et cliquez sur +', 'success', 2000);
        
        console.log('✅ Sélection rapide effectuée');
    }, 100);
}

/**
 * AMÉLIORATION 3 : Définir une quantité rapide
 */
function setQuickQuantity(packageId, quantity) {
    const normalizedId = normalizePackageId(packageId);
    const qtyInput = document.getElementById(`qty-input-${normalizedId}`);
    
    if (!qtyInput) {
        console.error('❌ Input quantité introuvable');
        return;
    }
    
    const max = parseInt(qtyInput.max);
    const actualQty = Math.min(quantity, max);
    
    qtyInput.value = actualQty;
    
    if (actualQty < quantity) {
        showMessage(`⚠️ Quantité limitée à ${actualQty} (max disponible)`, 'info', 2000);
    }
    
    // Mettre le focus sur le bouton "+"
    qtyInput.focus();
    
    console.log(`⚡ Quantité rapide définie: ${actualQty}`);
}

/**
 * AMÉLIORATION 3 : Définir la quantité maximum
 */
function setMaxQuantity(packageId) {
    const normalizedId = normalizePackageId(packageId);
    const qtyInput = document.getElementById(`qty-input-${normalizedId}`);
    
    if (!qtyInput) {
        console.error('❌ Input quantité introuvable');
        return;
    }
    
    const max = parseInt(qtyInput.max);
    qtyInput.value = max;
    
    showMessage(`✅ Quantité maximum définie: ${max}`, 'success', 2000);
    
    // Mettre le focus sur le bouton "+"
    qtyInput.focus();
    
    console.log(`⚡ Quantité max définie: ${max}`);
}

/**
 * AMÉLIORATION 1 : Ajouter un article standard à un colis avec AUTO-SAUVEGARDE
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
        showMessage('⚠️ Veuillez remplir tous les champs', 'error', 3000);
        return;
    }

    if (quantity > maxAllowed) {
        showMessage(`⚠️ Quantité trop élevée. Maximum: ${maxAllowed}`, 'error', 3000);
        qtyInput.value = maxAllowed;
        return;
    }

    const product = colisageApp.productData[productId];
    const detail = product.details.find(d => d.detail_id === detailId);
    
    if (!detail) {
        showMessage('❌ Détail non trouvé', 'error', 3000);
        return;
    }

    const existingItem = pkg.items.find(item => item.detailId === detailId);
    if (existingItem) {
        existingItem.quantity += quantity;
    } else {
        let productWeightInKg = convertWeightToKg(product.weight, product.weight_units);
        
        if (product.weight_units === 3 && product.weight > 1) {
            console.warn('⚠️ Détection: Poids', product.weight, 'avec unité "grammes" mais semble déjà être en kg/m²');
            productWeightInKg = product.weight;
        }
        
        const surfaceUnit = (detail.longueur * detail.largeur) / 1000000.0;
        const poidsTotal = quantity * surfaceUnit * productWeightInKg;
        const poidsUneUnite = surfaceUnit * productWeightInKg;
        
        pkg.items.push({
            productId: productId,
            detailId: detailId,
            quantity: quantity,
            longueur: detail.longueur,
            largeur: detail.largeur,
            description: detail.description || '',
            weight: poidsUneUnite,
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
    
    // Cacher les boutons de quantité rapide
    const quickQtyDiv = document.getElementById(`quick-qty-${normalizedId}`);
    if (quickQtyDiv) {
        quickQtyDiv.style.display = 'none';
    }

    render();
    console.log('✅ Article ajouté avec succès');
    
    // AMÉLIORATION 1 : AUTO-SAUVEGARDE après ajout
    autoSaveColisage();
}

/**
 * AMÉLIORATION 1 : Ajouter un article libre à un colis avec AUTO-SAUVEGARDE
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
        showMessage('⚠️ Veuillez remplir tous les champs obligatoires', 'error', 3000);
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
    console.log('✅ Article libre ajouté avec succès');
    
    // AMÉLIORATION 1 : AUTO-SAUVEGARDE après ajout
    autoSaveColisage();
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
    console.log('✅ Article supprimé');
    
    // AUTO-SAUVEGARDE après suppression
    autoSaveColisage();
}

/**
 * AMÉLIORATION 1 : Auto-sauvegarde intelligente avec debounce
 */
function autoSaveColisage() {
    // Annuler le timer précédent s'il existe
    if (window.autoSaveTimer) {
        clearTimeout(window.autoSaveTimer);
    }
    
    // Définir un nouveau timer de 500ms
    window.autoSaveTimer = setTimeout(() => {
        console.log('💾 Auto-sauvegarde déclenchée...');
        saveColisage(true); // true = mode auto
    }, 500);
}

/**
 * Sauvegarder le colisage (manuelle ou automatique)
 */
function saveColisage(isAuto = false) {
    if (colisageApp.packages.length === 0) {
        if (!isAuto) {
            showMessage('ℹ️ Aucun colis à enregistrer', 'info', 3000);
        }
        return;
    }
    
    // Éviter les sauvegardes multiples simultanées
    if (colisageApp.isSaving) {
        console.log('⏳ Sauvegarde déjà en cours, annulation...');
        return;
    }
    
    colisageApp.isSaving = true;
    
    console.log('🔄 Début de la sauvegarde...', isAuto ? '(automatique)' : '(manuelle)');
    
    // Afficher un indicateur discret pour l'auto-save
    let savingMessage = null;
    if (isAuto) {
        savingMessage = showMessage('<span class="loading-spinner"></span> Sauvegarde...', 'info', 0);
    } else {
        savingMessage = showMessage('<span class="loading-spinner"></span> Sauvegarde en cours...', 'info', 0);
    }
    
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
        colisageApp.isSaving = false;
        
        if (savingMessage && savingMessage.remove) {
            savingMessage.remove();
        }
        
        if (data.success) {
            console.log('✅ Colisage sauvegardé avec succès');
            
            if (!isAuto) {
                showMessage('✅ Colisage sauvegardé avec succès !', 'success', 3000);
            } else {
                // Pour l'auto-save, juste un message très discret
                showMessage('💾', 'success', 1000);
            }
        } else {
            console.error('❌ Erreur lors de la sauvegarde:', data.error);
            showMessage('❌ Erreur lors de la sauvegarde: ' + data.error, 'error', 5000);
        }
    })
    .catch(error => {
        colisageApp.isSaving = false;
        
        if (savingMessage && savingMessage.remove) {
            savingMessage.remove();
        }
        
        console.error('❌ Erreur de communication:', error);
        showMessage('❌ Erreur de communication avec le serveur', 'error', 5000);
    });
}

/**
 * NOUVEAU : Afficher un message flottant à l'utilisateur
 * 
 * Cette fonction crée des notifications flottantes en position fixed
 * avec animation et empilage automatique
 */
function showMessage(message, type = 'info', duration = 5000) {
    // Obtenir ou créer le container de notifications
    let container = document.querySelector('.colisage-notifications-container');
    
    if (!container) {
        container = createNotificationsContainer();
    }
    
    // Créer le message
    const messageDiv = document.createElement('div');
    messageDiv.className = `colisage-${type}`;
    messageDiv.innerHTML = message;
    
    // Ajouter le message au container (il s'empile automatiquement grâce au flex-direction: column)
    container.appendChild(messageDiv);
    
    console.log(`📢 Message ${type} affiché:`, message.replace(/<[^>]*>/g, '')); // Log sans HTML
    
    // Auto-suppression après duration (si duration > 0)
    if (duration > 0) {
        setTimeout(() => {
            // Ajouter la classe d'animation de sortie
            messageDiv.classList.add('colisage-message-exit');
            
            // Supprimer l'élément après l'animation (300ms selon le CSS)
            setTimeout(() => {
                if (messageDiv.parentNode) {
                    messageDiv.remove();
                }
            }, 300);
        }, duration);
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
        console.log(`  ${index + 1}. ID: ${pkg.id} - Articles: ${pkg.items.length}`);
    });
    
    console.log(`🎯 Colis actuellement sélectionné: ${colisageApp.selectedPackageId}`);
    
    if (colisageApp.selectedPackageId) {
        const foundPkg = findPackageById(colisageApp.selectedPackageId);
        console.log(`🔍 Résultat de la recherche:`, foundPkg ? '✅ Trouvé' : '❌ Non trouvé');
        
        if (foundPkg) {
            console.log(`📝 Détails du colis trouvé:`, {
                id: foundPkg.id,
                items: foundPkg.items.length,
                isFree: foundPkg.isFree
            });
        }
    }
    
    const editorContainer = document.getElementById('colis-editor');
    console.log(`🖥️ Conteneur éditeur:`, editorContainer ? '✅ Présent' : '❌ Absent');
    
    if (editorContainer) {
        const hasEmptyEditor = editorContainer.querySelector('.empty-editor');
        const hasEditorTitle = editorContainer.querySelector('.colis-editor-title');
        console.log(`📝 État de l'éditeur:`, hasEmptyEditor ? '⚪ Vide' : (hasEditorTitle ? '✅ Actif' : '❓ Inconnu'));
    }
    
    console.groupEnd();
    
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
window.quickSelectDetail = quickSelectDetail;
window.setQuickQuantity = setQuickQuantity;
window.setMaxQuantity = setMaxQuantity;
window.saveColisage = saveColisage;

// Exposer les fonctions d'édition des titres
window.startEditCardTitle = startEditCardTitle;
window.saveCardTitle = saveCardTitle;
window.cancelEditCardTitle = cancelEditCardTitle;

// Exposer les fonctions de diagnostic
window.debugColisageSelection = debugColisageSelection;

console.log('✅ Fonctionnalités d\'édition des titres chargées (Service ID=361)');
