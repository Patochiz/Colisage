/**
 * Module Colisage - Sélection des lots par ligne de commande
 *
 * Bouton "LOT" dans le récapitulatif des produits -> popup listant les lots en stock
 * du produit (tous entrepôts) avec une case quantité par lot.
 * La sélection est enregistrée dans llx_colisage_lots et sert à pré-remplir
 * les quantités par lot à la création de l'expédition.
 */
(function () {
    'use strict';

    function esc(v) {
        return String(v === null || v === undefined ? '' : v)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    }

    function fmt(n) {
        const x = Number(n) || 0;
        return (Math.round(x * 1000) / 1000).toLocaleString('fr-FR');
    }

    function data() {
        return window.colisageData || {};
    }

    function selectionsFor(commandedetId) {
        const sel = data().lotSelections || {};
        return sel[commandedetId] || sel[String(commandedetId)] || [];
    }

    function selectedTotal(commandedetId) {
        return selectionsFor(commandedetId).reduce((t, s) => t + (Number(s.qty) || 0), 0);
    }

    /**
     * Rendu du bouton LOT (appelé par renderProductsGroup dans colisage.js)
     */
    window.colisageRenderLotButton = function (productId, product) {
        if (!data().lotsEnabled || !product || !product.tobatch) return '';
        const lineId = product.commandedet_id;
        const total = selectedTotal(lineId);
        const qty = Number(product.qty) || 0;
        let state = 'lot-btn-none';
        let label = 'LOT';
        let title = 'Aucun lot choisi : Dolibarr proposera ses lots par défaut à l\'expédition';
        if (total > 0) {
            label = 'LOT ' + fmt(total) + '/' + fmt(qty);
            if (Math.abs(total - qty) < 0.0001) {
                state = 'lot-btn-ok';
                title = 'Lots choisis pour toute la quantité commandée';
            } else {
                state = 'lot-btn-partial';
                title = 'Lots choisis pour ' + fmt(total) + ' sur ' + fmt(qty) + ' commandé(s)';
            }
        }
        return `<button type="button" class="colisage-lot-btn ${state}" title="${esc(title)}"
                    onclick="colisageOpenLots(${Number(lineId)})">${esc(label)}</button>`;
    };

    function findProductByLine(lineId) {
        const pd = data().productData || {};
        for (const k in pd) {
            if (Number(pd[k].commandedet_id) === Number(lineId)) return pd[k];
        }
        return null;
    }

    function closeModal() {
        const m = document.getElementById('colisage-lot-modal');
        if (m) m.remove();
        document.removeEventListener('keydown', onKey);
    }

    function onKey(e) {
        if (e.key === 'Escape') closeModal();
    }

    function notify(msg, type) {
        if (typeof window.showMessage === 'function') {
            window.showMessage(msg, type || 'info');
        } else if (typeof showMessage === 'function') {
            showMessage(msg, type || 'info');
        } else {
            alert(msg.replace(/<[^>]*>/g, ''));
        }
    }

    function buildModalShell(product) {
        closeModal();
        const wrap = document.createElement('div');
        wrap.id = 'colisage-lot-modal';
        wrap.className = 'colisage-lot-overlay';
        wrap.innerHTML = `
            <div class="colisage-lot-dialog" role="dialog" aria-modal="true">
                <div class="colisage-lot-header">
                    <div>
                        <div class="colisage-lot-title">Lots à expédier</div>
                        <div class="colisage-lot-subtitle">${esc(product ? product.name : '')}</div>
                    </div>
                    <button type="button" class="colisage-lot-close" title="Fermer">✕</button>
                </div>
                <div class="colisage-lot-body"><div class="colisage-loading">Chargement des lots...</div></div>
                <div class="colisage-lot-footer"></div>
            </div>`;
        document.body.appendChild(wrap);
        wrap.addEventListener('click', (e) => { if (e.target === wrap) closeModal(); });
        wrap.querySelector('.colisage-lot-close').addEventListener('click', closeModal);
        document.addEventListener('keydown', onKey);
        return wrap;
    }

    function renderLots(wrap, d) {
        const body = wrap.querySelector('.colisage-lot-body');
        const footer = wrap.querySelector('.colisage-lot-footer');
        const canEdit = !!d.can_edit;
        const allowNeg = !!d.allow_negative;

        if (!d.lots || d.lots.length === 0) {
            body.innerHTML = '<div class="colisage-lot-empty">Aucun lot en stock pour ce produit.</div>';
            footer.innerHTML = '<button type="button" class="colisage-btn colisage-btn-secondary" data-act="close">Fermer</button>';
            footer.querySelector('[data-act="close"]').addEventListener('click', closeModal);
            return;
        }

        let rows = '';
        d.lots.forEach((l, i) => {
            const dates = [l.sellby ? 'DLV ' + esc(l.sellby) : '', l.eatby ? 'DLC ' + esc(l.eatby) : ''].filter(Boolean).join(' · ');
            const shipped = Number(l.shipped_qty) || 0;
            rows += `
                <tr class="${l.missing ? 'lot-missing' : ''}${Number(l.selected_qty) > 0 ? ' lot-selected' : ''}">
                    <td><strong>${esc(l.batch)}</strong>${dates ? '<div class="lot-dates">' + dates + '</div>' : ''}
                        ${l.missing ? '<div class="lot-warning">Plus en stock</div>' : ''}</td>
                    <td>${esc(l.entrepot)}</td>
                    <td class="right">${fmt(l.qty)}</td>
                    <td class="right">${shipped > 0 ? fmt(shipped) : ''}</td>
                    <td class="right">
                        <input type="number" class="lot-qty-input" min="0" step="any" data-idx="${i}"
                               value="${Number(l.selected_qty) > 0 ? Number(l.selected_qty) : ''}"
                               ${canEdit ? '' : 'disabled'} placeholder="0">
                        ${canEdit && !l.missing ? `<button type="button" class="lot-fill-btn" data-idx="${i}" title="Prendre le reste à affecter sur ce lot">↵</button>` : ''}
                    </td>
                </tr>`;
        });

        body.innerHTML = `
            <table class="colisage-lot-table">
                <thead><tr>
                    <th>Lot</th><th>Entrepôt</th><th class="right">Stock</th>
                    <th class="right" title="Déjà mis sur une expédition de cette commande">Déjà expédié</th>
                    <th class="right">Qté à expédier</th>
                </tr></thead>
                <tbody>${rows}</tbody>
            </table>
            <div class="colisage-lot-total"></div>`;

        footer.innerHTML = `
            ${canEdit ? '<button type="button" class="colisage-btn colisage-btn-secondary" data-act="clear">Tout effacer</button>' : ''}
            <span class="colisage-lot-footer-spacer"></span>
            <button type="button" class="colisage-btn colisage-btn-secondary" data-act="close">${canEdit ? 'Annuler' : 'Fermer'}</button>
            ${canEdit ? '<button type="button" class="colisage-btn colisage-btn-primary" data-act="save">Enregistrer</button>' : ''}`;

        const inputs = Array.from(body.querySelectorAll('.lot-qty-input'));
        const totalDiv = body.querySelector('.colisage-lot-total');
        const qtyOrdered = Number(d.qty_ordered) || 0;

        function currentTotal() {
            return inputs.reduce((t, inp) => t + (parseFloat(String(inp.value).replace(',', '.')) || 0), 0);
        }

        function refresh() {
            let hasError = false;
            inputs.forEach((inp) => {
                const l = d.lots[Number(inp.dataset.idx)];
                const v = parseFloat(String(inp.value).replace(',', '.')) || 0;
                const over = !allowNeg && v > (Number(l.qty) || 0);
                inp.classList.toggle('lot-qty-error', over);
                inp.title = over ? 'Quantité supérieure au stock du lot' : '';
                if (over) hasError = true;
                inp.closest('tr').classList.toggle('lot-selected', v > 0);
            });
            const t = currentTotal();
            let cls = 'lot-total-ok';
            let msg = `Total sélectionné : <strong>${fmt(t)}</strong> / ${fmt(qtyOrdered)} commandé(s)`;
            if (t === 0) {
                cls = 'lot-total-none';
                msg += ' — aucun lot imposé, Dolibarr gardera son choix par défaut';
            } else if (t < qtyOrdered - 0.0001) {
                cls = 'lot-total-partial';
                msg += ` — reste ${fmt(qtyOrdered - t)} à affecter`;
            } else if (t > qtyOrdered + 0.0001) {
                cls = 'lot-total-over';
                msg += ' — supérieur à la quantité commandée';
            }
            totalDiv.className = 'colisage-lot-total ' + cls;
            totalDiv.innerHTML = msg;
            const saveBtn = footer.querySelector('[data-act="save"]');
            if (saveBtn) saveBtn.disabled = hasError;
        }

        inputs.forEach((inp) => inp.addEventListener('input', refresh));
        body.querySelectorAll('.lot-fill-btn').forEach((btn) => {
            btn.addEventListener('click', () => {
                const idx = Number(btn.dataset.idx);
                const inp = inputs.find((x) => Number(x.dataset.idx) === idx);
                const l = d.lots[idx];
                const others = currentTotal() - (parseFloat(String(inp.value).replace(',', '.')) || 0);
                let rest = Math.max(0, qtyOrdered - others);
                if (!allowNeg) rest = Math.min(rest, Number(l.qty) || 0);
                inp.value = rest > 0 ? Math.round(rest * 1000) / 1000 : '';
                refresh();
            });
        });

        footer.querySelector('[data-act="close"]').addEventListener('click', closeModal);
        const clearBtn = footer.querySelector('[data-act="clear"]');
        if (clearBtn) clearBtn.addEventListener('click', () => { inputs.forEach((i) => { i.value = ''; }); refresh(); });
        const saveBtn = footer.querySelector('[data-act="save"]');
        if (saveBtn) {
            saveBtn.addEventListener('click', () => {
                const lots = [];
                inputs.forEach((inp) => {
                    const v = parseFloat(String(inp.value).replace(',', '.')) || 0;
                    if (v > 0) {
                        const l = d.lots[Number(inp.dataset.idx)];
                        lots.push({ fk_entrepot: l.fk_entrepot, batch: l.batch, qty: v });
                    }
                });
                saveLots(d.commandedet_id, lots, saveBtn);
            });
        }
        refresh();
        const first = inputs.find((i) => !i.disabled);
        if (first) first.focus();
    }

    function saveLots(lineId, lots, btn) {
        btn.disabled = true;
        btn.textContent = 'Enregistrement...';
        jQuery.ajax({
            url: data().urlBase + '/ajax/lots.php',
            method: 'POST',
            dataType: 'json',
            data: {
                action: 'save',
                token: data().token,
                commande_id: data().commandeId,
                commandedet_id: lineId,
                lots: JSON.stringify(lots)
            }
        }).done((r) => {
            if (r && r.success) {
                if (!data().lotSelections || Array.isArray(data().lotSelections)) data().lotSelections = {};
                data().lotSelections[lineId] = r.data.selections || [];
                closeModal();
                if (typeof window.renderProductsSummary === 'function') window.renderProductsSummary();
                else if (typeof renderProductsSummary === 'function') renderProductsSummary();
                notify('✅ Lots enregistrés', 'success');
            } else {
                btn.disabled = false;
                btn.textContent = 'Enregistrer';
                notify('❌ ' + esc((r && r.error) || 'Erreur inconnue').replace(/\n/g, '<br>'), 'error');
            }
        }).fail((xhr) => {
            btn.disabled = false;
            btn.textContent = 'Enregistrer';
            notify('❌ Erreur réseau (' + xhr.status + ')', 'error');
        });
    }

    /**
     * Ouvre le popup des lots pour une ligne de commande
     */
    window.colisageOpenLots = function (lineId) {
        const product = findProductByLine(lineId);
        const wrap = buildModalShell(product);
        jQuery.ajax({
            url: data().urlBase + '/ajax/lots.php',
            method: 'GET',
            dataType: 'json',
            data: { action: 'list', commande_id: data().commandeId, commandedet_id: lineId }
        }).done((r) => {
            if (!document.body.contains(wrap)) return;
            if (r && r.success) {
                renderLots(wrap, r.data);
            } else {
                wrap.querySelector('.colisage-lot-body').innerHTML =
                    '<div class="colisage-lot-empty">' + esc((r && r.error) || 'Erreur') + '</div>';
            }
        }).fail((xhr) => {
            wrap.querySelector('.colisage-lot-body').innerHTML =
                '<div class="colisage-lot-empty">Erreur réseau (' + xhr.status + ')</div>';
        });
    };
})();
