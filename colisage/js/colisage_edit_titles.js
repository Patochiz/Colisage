/* Copyright (C) 2025 Patrice GOURMELEN <pgourmelen@diamant-industrie.com>
 *
 * AJOUT : Fonctionnalité d'édition des titres de cartes
 * 
 * À ajouter à la fin du fichier colisage.js existant
 */

/**
 * MODIFICATION DE LA FONCTION renderProductsGroup
 * Remplacez la fonction renderProductsGroup existante par celle-ci
 */
function renderProductsGroup(productIds, titre = null, description = null, sectionIndex = null) {
    let html = '';
    
    // Afficher le titre de section si présent
    if (titre && sectionIndex !== null) {
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
 * Démarrer l'édition d'un titre de carte
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
    
    const currentTitle = textElement.textContent;
    
    // Remplacer le texte par un input
    textElement.innerHTML = `<input type="text" class="section-titre-edit-input" id="section-titre-edit-input-${sectionIndex}" value="${currentTitle}">`;
    
    // Remplacer les boutons d'action
    actionsElement.innerHTML = `
        <button class="section-titre-save-btn" onclick="saveCardTitle(${sectionIndex})" title="Sauvegarder">
            ✓
        </button>
        <button class="section-titre-cancel-btn" onclick="cancelEditCardTitle(${sectionIndex}, '${currentTitle.replace(/'/g, "\\'")}' )" title="Annuler">
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
                saveCardTitle(sectionIndex);
            } else if (e.key === 'Escape') {
                cancelEditCardTitle(sectionIndex, currentTitle);
            }
        });
    }
};

/**
 * Sauvegarder le nouveau titre
 */
window.saveCardTitle = function(sectionIndex) {
    console.log('💾 Sauvegarde titre section', sectionIndex);
    
    const section = window.colisageData.sections[sectionIndex];
    if (!section) {
        console.error('❌ Section introuvable');
        return;
    }
    
    const inputElement = document.getElementById(`section-titre-edit-input-${sectionIndex}`);
    if (!inputElement) {
        console.error('❌ Input introuvable');
        return;
    }
    
    const newTitle = inputElement.value.trim();
    
    if (!newTitle) {
        showMessage('⚠️ Le titre ne peut pas être vide', 'error', 3000);
        inputElement.focus();
        return;
    }
    
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
 * Annuler l'édition
 */
window.cancelEditCardTitle = function(sectionIndex, originalTitle) {
    console.log('✗ Annulation édition titre section', sectionIndex);
    
    // Re-rendre le récapitulatif pour restaurer l'état initial
    renderProductsSummary();
};

console.log('✅ Fonctionnalités d\'édition des titres chargées');
