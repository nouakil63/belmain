/* Belmains — client-facing product editor. No draft is published implicitly. */
(function () {
    'use strict';

    const boot = window.BelmainsBuilder;
    const root = document.getElementById('belmains-builder');
    if (!boot || !root) return;

    const priceKeys = new Set(['regular_price', 'sale_price', 'duo_price', 'duo_compare_price']);
    const state = { products: [], payload: null, values: {}, section: 0, dirty: false, busy: false, conflict: false, message: '', messageType: 'info' };
    let serial = 0;
    let notice, status, actions, productSelect;

    function el(tag, attrs, children) {
        const node = document.createElement(tag);
        Object.entries(attrs || {}).forEach(function (entry) {
            const key = entry[0], value = entry[1];
            if (value === undefined || value === null || value === false) return;
            if (key === 'class') node.className = value;
            else if (key === 'text') node.textContent = value;
            else if (key === 'on') Object.entries(value).forEach(function (event) { node.addEventListener(event[0], event[1]); });
            else if (key === 'checked' || key === 'disabled') node[key] = Boolean(value);
            else node.setAttribute(key, String(value));
        });
        (Array.isArray(children) ? children : [children]).forEach(function (child) {
            if (child !== undefined && child !== null) node.append(child.nodeType ? child : document.createTextNode(String(child)));
        });
        return node;
    }

    function button(label, handler, className, attrs) {
        return el('button', Object.assign({ type: 'button', class: 'bm-btn ' + (className || ''), text: label, on: { click: handler } }, attrs || {}));
    }

    function clone(value) { return JSON.parse(JSON.stringify(value)); }
    function safeUrl(value) {
        if (typeof value !== 'string' || !value) return '';
        try { const url = new URL(value, window.location.href); return /^(https?:)$/.test(url.protocol) ? url.href : ''; }
        catch (_) { return ''; }
    }
    function adminUrl(path) { return safeUrl(String(boot.adminUrl || '').replace(/\/?$/, '/') + path); }
    function currentSection() { return state.payload.schema[state.section]; }
    function sectionLabel(section) { return section.label.replace(/^\d+\.\s*/, ''); }

    /* Copy only supported nodes into the editor. Never insert supplied HTML. */
    function richNodes(value) {
        const source = new DOMParser().parseFromString(String(value || ''), 'text/html');
        const fragment = document.createDocumentFragment();
        function copy(from, into) {
            Array.from(from.childNodes).forEach(function (node) {
                if (node.nodeType === 3) { into.append(document.createTextNode(node.nodeValue)); return; }
                if (node.nodeType !== 1) return;
                const tag = node.tagName.toLowerCase();
                if (['script', 'style', 'iframe', 'object', 'svg', 'math', 'template'].includes(tag)) return;
                if (tag === 'br') { into.append(document.createElement('br')); return; }
                if (['strong', 'b', 'em', 'i'].includes(tag)) {
                    const child = document.createElement(tag === 'b' ? 'strong' : tag === 'i' ? 'em' : tag);
                    copy(node, child); into.append(child);
                } else {
                    if ((tag === 'div' || tag === 'p') && into.childNodes.length && into.lastChild.nodeName !== 'BR') into.append(document.createElement('br'));
                    copy(node, into);
                }
            });
        }
        copy(source.body, fragment); return fragment;
    }

    function serializeRich(input) {
        function text(value) { return value.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/\u00a0/g, ' ').replace(/\r\n|\r|\n/g, '<br>'); }
        function serialize(parent) {
            let result = '';
            Array.from(parent.childNodes).forEach(function (node) {
                if (node.nodeType === 3) { result += text(node.nodeValue); return; }
                if (node.nodeType !== 1) return;
                const tag = node.tagName.toLowerCase();
                if (['script', 'style', 'iframe', 'object', 'svg', 'math', 'template'].includes(tag)) return;
                if (tag === 'br') { result += '<br>'; return; }
                const content = serialize(node);
                if (tag === 'strong' || tag === 'b') result += '<strong>' + content + '</strong>';
                else if (tag === 'em' || tag === 'i') result += '<em>' + content + '</em>';
                else result += (['div', 'p'].includes(tag) && result && !result.endsWith('<br>') ? '<br>' : '') + content;
            });
            return result;
        }
        return serialize(input);
    }
    function markDirty() {
        if (!state.dirty) { state.dirty = true; refreshStatus(); refreshActions(); }
    }

    async function request(path, method, body) {
        let response;
        try {
            response = await fetch(String(boot.api).replace(/\/$/, '') + path, {
                method: method || 'GET', credentials: 'same-origin',
                headers: { 'X-WP-Nonce': boot.nonce, 'Content-Type': 'application/json' },
                body: body === undefined ? undefined : JSON.stringify(body)
            });
        } catch (_) { throw new Error('La connexion a été interrompue. Vos saisies sont conservées dans cet onglet. Réessayez.'); }
        let result;
        try { result = await response.json(); }
        catch (_) { throw new Error('La boutique ne répond pas correctement. Vos saisies sont conservées. Réessayez dans un instant.'); }
        if (!response.ok) {
            const error = new Error(result.message || 'Cette action n’a pas abouti. Réessayez.');
            error.status = response.status;
            error.field = result.data && result.data.field;
            throw error;
        }
        return result;
    }

    function setMessage(message, type, focus) {
        state.message = message; state.messageType = type || 'info';
        refreshNotice();
        if (focus && notice) notice.focus();
    }

    function fail(error) {
        if (error.status === 409) {
            state.conflict = true;
            setMessage('Cette fiche a été modifiée dans un autre onglet. Vos saisies restent ici. Rechargez la fiche avant de continuer pour ne pas écraser la dernière version.', 'error', true);
        } else if (error.status === 401 || error.status === 403) {
            setMessage('Votre session a expiré ou vous n’avez plus accès à cette fiche. Gardez cet onglet ouvert et reconnectez-vous à WordPress dans un autre onglet, puis réessayez.', 'error', true);
        } else {
            setMessage(error.message, 'error', true);
        }
        if (error.field) {
            const fieldKey = String(error.field).split(/[.\[]/)[0];
            const index = state.payload && state.payload.schema.findIndex(function (section) { return section.fields.some(function (field) { return field.key === fieldKey; }); });
            if (index >= 0) { state.section = index; render(); }
            const fields = Array.from(root.querySelectorAll('[data-field]'));
            const field = fields.find(function (node) { return node.dataset.field === String(error.field); }) || fields.find(function (node) { return node.dataset.field === fieldKey || node.dataset.field.startsWith(fieldKey + '.'); });
            if (field) {
                const card = field.closest('details'); if (card) card.open = true;
                field.setAttribute('aria-invalid', 'true');
                window.setTimeout(function () { if (field.isConnected) field.focus(); }, 0);
            } else if (notice) notice.focus();
        }
        refreshActions();
    }

    function setBusy(value) {
        state.busy = value;
        root.setAttribute('aria-busy', value ? 'true' : 'false');
        root.querySelectorAll('.bm-editor-fields input, .bm-editor-fields textarea, .bm-editor-fields select, .bm-editor-fields button, .bm-product-picker select, .bm-product-picker button').forEach(function (node) {
            if (value) { node.dataset.wasDisabled = node.disabled ? 'yes' : 'no'; node.disabled = true; }
            else if (node.dataset.wasDisabled !== undefined) { node.disabled = node.dataset.wasDisabled === 'yes'; delete node.dataset.wasDisabled; }
        });
        root.querySelectorAll('.bm-rich-input').forEach(function (node) { node.contentEditable = value ? 'false' : 'true'; node.setAttribute('aria-disabled', value ? 'true' : 'false'); });
        refreshActions(); refreshStatus();
    }

    function acceptPayload(payload) {
        if (!payload || !Array.isArray(payload.schema) || !payload.schema.length || !payload.values) throw new Error('Cette fiche ne peut pas être ouverte. Rechargez la page ou contactez votre administrateur.');
        state.payload = payload; state.values = clone(payload.values); state.dirty = false; state.conflict = false;
        state.section = Math.min(state.section, payload.schema.length - 1);
        const item = {
            id: payload.id, name: payload.values.product_name || 'Produit sans nom', status: payload.status,
            active: Boolean(payload.active), has_draft: Boolean(payload.has_draft), stock_quantity: payload.stock_quantity
        };
        if (payload.active) state.products.forEach(function (product) { product.active = Number(product.id) === Number(payload.id); });
        const index = state.products.findIndex(function (product) { return Number(product.id) === Number(payload.id); });
        if (index < 0) state.products.push(item); else state.products[index] = Object.assign({}, state.products[index], item);
    }

    async function loadProduct(id) {
        if (state.busy) return;
        setBusy(true);
        try { acceptPayload(await request('/products/' + Number(id))); state.message = ''; render(); }
        catch (error) { fail(error); }
        finally { setBusy(false); }
    }

    function dialog(options) {
        return new Promise(function (resolve) {
            const previousFocus = document.activeElement;
            const headingId = 'bm-dialog-' + (++serial);
            const box = el('dialog', { class: 'bm-dialog', 'aria-labelledby': headingId });
            const heading = el('h2', { id: headingId, text: options.title });
            const close = function (choice) {
                if (typeof box.close === 'function') box.close();
                box.remove();
                if (previousFocus && previousFocus.isConnected) previousFocus.focus();
                resolve(choice);
            };
            const controls = el('div', { class: 'bm-dialog-actions' });
            options.choices.forEach(function (choice) {
                controls.append(button(choice.label, function () { close(choice.value); }, choice.primary ? 'bm-btn-primary' : choice.danger ? 'bm-btn-danger' : 'bm-btn-secondary'));
            });
            box.append(heading, el('p', { text: options.text }), controls);
            box.addEventListener('cancel', function (event) { event.preventDefault(); close(null); });
            document.body.append(box);
            box.showModal();
            const initial = controls.querySelector('.bm-btn-secondary') || controls.querySelector('button');
            if (initial) initial.focus();
        });
    }

    async function leaveDraft() {
        if (!state.dirty) return true;
        const choice = await dialog({
            title: 'Enregistrer vos modifications ?',
            text: 'Votre dernière saisie n’est pas encore enregistrée. Vous pouvez la garder dans un brouillon avant de changer de produit.',
            choices: [
                { label: 'Continuer la modification', value: 'cancel' },
                { label: 'Quitter sans enregistrer', value: 'leave', danger: true },
                { label: 'Enregistrer le brouillon', value: 'save', primary: true }
            ]
        });
        if (choice === 'save') return Boolean(await save('draft'));
        return choice === 'leave';
    }

    function outgoingValues() {
        const values = clone(state.values);
        priceKeys.forEach(function (key) {
            if (values[key] === undefined) return;
            const value = String(values[key]).trim().replace(',', '.');
            if (value && !/^\d+(\.\d{1,2})?$/.test(value)) {
                const error = new Error('Indiquez un prix en euros avec deux décimales maximum, par exemple 89,99.');
                error.field = key; throw error;
            }
            values[key] = value;
        });
        return values;
    }

    async function save(action, activate) {
        if (state.busy || state.conflict || !state.payload) return null;
        let values;
        try { values = outgoingValues(); } catch (error) { fail(error); return null; }
        setBusy(true);
        try {
            const payload = await request('/products/' + state.payload.id, 'POST', { revision: state.payload.revision, values: values, action: action, activate: Boolean(activate) });
            acceptPayload(payload);
            state.messageType = 'success';
            state.message = action === 'publish' ? 'La page est publiée. Vos modifications sont visibles sur la boutique.' : 'Brouillon enregistré. Vous pouvez reprendre vos modifications plus tard.';
            render();
            return payload;
        } catch (error) { fail(error); return null; }
        finally { setBusy(false); }
    }

    async function publish() {
        if (state.busy || state.conflict) return;
        const activate = !state.payload.active;
        if (activate) {
            const current = state.products.find(function (product) { return product.active; });
            const choice = await dialog({
                title: 'Afficher ce produit sur la boutique ?',
                text: '« ' + (state.values.product_name || 'Ce produit') + ' » remplacera ' + (current ? '« ' + current.name + ' »' : 'le produit actuel') + ' sur la page d’accueil. L’ancien produit et ses commandes seront conservés. Vérifiez le stock avant de publier.',
                choices: [{ label: 'Continuer la préparation', value: 'cancel' }, { label: 'Publier et afficher ce produit', value: 'publish', primary: true }]
            });
            if (choice !== 'publish') return;
        }
        await save('publish', activate);
    }

    async function preview() {
        if (state.busy || state.conflict) return;
        const previewWindow = window.open('about:blank', '_blank');
        if (previewWindow) previewWindow.opener = null;
        const payload = state.dirty || !state.payload.has_draft ? await save('draft') : state.payload;
        const url = payload && safeUrl(payload.preview_url);
        if (!url) { if (previewWindow) previewWindow.close(); if (payload) setMessage('L’aperçu n’a pas pu être ouvert. Enregistrez à nouveau le brouillon, puis réessayez.', 'error', true); return; }
        if (previewWindow) previewWindow.location.href = url;
        else {
            setMessage('Brouillon enregistré. Votre navigateur a bloqué le nouvel onglet : utilisez le lien « Ouvrir l’aperçu » ci-dessous.', 'info', true);
            notice.append(el('a', { class: 'bm-inline-link', href: url, target: '_blank', rel: 'noopener', text: 'Ouvrir l’aperçu' }));
        }
    }

    async function duplicate() {
        if (state.busy || state.conflict) return;
        const choice = await dialog({
            title: 'Préparer un nouveau produit',
            text: 'Nous recopions les textes, les visuels et les offres de cette fiche dans un nouveau brouillon. Le stock du nouveau produit sera à zéro. La boutique actuelle restera visible pendant votre préparation.',
            choices: [{ label: 'Annuler', value: 'cancel' }, { label: 'Créer le nouveau brouillon', value: 'create', primary: true }]
        });
        if (choice !== 'create') return;
        if (state.dirty && !(await save('draft'))) return;
        setBusy(true);
        try {
            acceptPayload(await request('/products/' + state.payload.id + '/duplicate', 'POST', { revision: state.payload.revision }));
            state.section = 0; state.messageType = 'success';
            state.message = 'Votre nouveau produit est prêt à personnaliser. Commencez par son nom, ses prix et ses visuels. Son stock est à zéro.';
            render();
            const name = root.querySelector('[data-field="product_name"]');
            if (name) { name.focus(); if (name.select) name.select(); }
        } catch (error) { fail(error); }
        finally { setBusy(false); }
    }

    async function discard() {
        if (state.busy || state.conflict) return;
        const choice = await dialog({
            title: 'Revenir à la version publiée ?',
            text: 'Les modifications de ce brouillon et les saisies non enregistrées seront supprimées. La version publiée restera inchangée.',
            choices: [{ label: 'Garder le brouillon', value: 'cancel' }, { label: 'Supprimer les modifications', value: 'discard', danger: true }]
        });
        if (choice !== 'discard') return;
        setBusy(true);
        try {
            acceptPayload(await request('/products/' + state.payload.id + '/discard', 'POST', { revision: state.payload.revision }));
            state.message = 'Vous avez retrouvé la version publiée.'; state.messageType = 'success'; render();
        } catch (error) { fail(error); }
        finally { setBusy(false); }
    }

    async function reloadConflict() {
        const choice = await dialog({
            title: 'Recharger la dernière version ?',
            text: 'Les saisies non enregistrées de cet onglet seront remplacées par la dernière version enregistrée. Copiez les textes à conserver avant de continuer.',
            choices: [{ label: 'Garder mes saisies', value: 'cancel' }, { label: 'Recharger la fiche', value: 'reload', primary: true }]
        });
        if (choice === 'reload') await loadProduct(state.payload.id);
    }

    function refreshStatus() {
        if (!status) return;
        const text = state.busy ? 'Enregistrement en cours…' : state.dirty ? 'Modifications non enregistrées' : state.payload.has_draft ? 'Brouillon enregistré' : state.payload.status === 'publish' ? 'Version publiée' : 'Produit en préparation';
        status.textContent = text;
        status.className = 'bm-save-state' + (state.dirty ? ' is-dirty' : '') + (state.busy ? ' is-busy' : '');
    }

    function refreshNotice() {
        if (!notice) return;
        notice.replaceChildren();
        notice.hidden = !state.message;
        notice.className = 'bm-notice bm-notice-' + state.messageType;
        notice.setAttribute('role', state.messageType === 'error' ? 'alert' : 'status');
        if (state.message) notice.append(el('p', { text: state.message }));
        if (state.conflict) notice.append(button('Recharger la fiche', reloadConflict, 'bm-btn-secondary'));
    }

    function refreshActions() {
        if (!actions || !state.payload) return;
        actions.replaceChildren();
        const disabled = state.busy || state.conflict;
        const copy = el('div', { class: 'bm-actions-copy' }, [
            el('strong', { text: state.payload.active ? 'Produit affiché sur la boutique' : 'Produit en préparation' }),
            el('span', { text: 'Vos visiteurs voient uniquement la version publiée.' })
        ]);
        const controls = el('div', { class: 'bm-action-buttons' }, [
            button('Enregistrer le brouillon', function () { save('draft'); }, 'bm-btn-secondary', { disabled: disabled || !state.dirty, 'data-action': 'save' }),
            button('Prévisualiser', preview, 'bm-btn-secondary', { disabled: disabled, 'data-action': 'preview' }),
            button(state.payload.active ? 'Publier les modifications' : 'Publier et afficher ce produit', publish, 'bm-btn-primary', { disabled: disabled, 'data-action': 'publish' })
        ]);
        actions.append(copy, controls);
    }

    function fieldValue(values, field) { return values[field.key] === undefined ? clone(field.default === undefined ? (field.type === 'checkbox' ? false : field.type === 'repeater' ? [] : '') : field.default) : values[field.key]; }

    function renderField(field, values, prefix, changed) {
        const id = 'bm-field-' + (++serial);
        const path = prefix ? prefix + '.' + field.key : field.key;
        const value = fieldValue(values, field);
        const wrapper = el('div', { class: 'bm-field bm-field-' + field.type + (priceKeys.has(field.key) ? ' bm-field-price' : ''), 'data-field-wrap': path });
        const label = el('label', { class: 'bm-field-label', for: id, text: field.label });
        const helpId = id + '-help';
        function update(next) { values[field.key] = next; changed(); }
        const common = { id: id, 'data-field': path, 'aria-describedby': field.help ? helpId : undefined };

        if (field.type === 'repeater') {
            wrapper.append(el('div', { class: 'bm-field-label', id: id, text: field.label }));
            if (field.help) wrapper.append(el('p', { class: 'bm-field-help', id: helpId, text: field.help }));
            const rows = Array.isArray(value) ? value : [];
            values[field.key] = rows;
            const list = el('div', { class: 'bm-repeater', role: 'group', 'aria-labelledby': id });
            const max = Number(field.max_items) || 30;
            function drawRows(focusIndex) {
                list.replaceChildren();
                if (!rows.length) list.append(el('p', { class: 'bm-empty-inline', text: 'Aucun élément pour le moment. Ajoutez-en un pour commencer.' }));
                rows.forEach(function (row, index) {
                    const rowId = id + '-row-' + index;
                    const card = el('details', { class: 'bm-repeat-card', open: focusIndex === undefined ? index === 0 : focusIndex === index });
                    const firstText = (field.fields || []).find(function (item) { return item.type === 'text'; }) || (field.fields || []).find(function (item) { return item.type === 'textarea'; });
                    const name = firstText && String(row[firstText.key] || '').trim();
                    const cardTitle = el('strong', { id: rowId, text: name ? (index + 1) + '. ' + name : 'Élément ' + (index + 1) });
                    const cardControls = el('div', { class: 'bm-repeat-actions' });
                    cardControls.append(
                        button('↑', function () { const temp = rows[index - 1]; rows[index - 1] = rows[index]; rows[index] = temp; update(rows); drawRows(index - 1); }, 'bm-icon-button', { disabled: index === 0, 'aria-label': 'Monter l’élément ' + (index + 1), title: 'Monter' }),
                        button('↓', function () { const temp = rows[index + 1]; rows[index + 1] = rows[index]; rows[index] = temp; update(rows); drawRows(index + 1); }, 'bm-icon-button', { disabled: index === rows.length - 1, 'aria-label': 'Descendre l’élément ' + (index + 1), title: 'Descendre' }),
                        button('Retirer', async function () {
                            const choice = await dialog({ title: 'Retirer cet élément ?', text: 'Il sera retiré de votre brouillon. La version publiée ne changera qu’après publication.', choices: [{ label: 'Garder', value: 'cancel' }, { label: 'Retirer', value: 'remove', danger: true }] });
                            if (choice === 'remove') { rows.splice(index, 1); update(rows); drawRows(Math.min(index, rows.length - 1)); }
                        }, 'bm-btn-text bm-remove', { 'aria-label': 'Retirer l’élément ' + (index + 1) })
                    );
                    card.append(el('summary', { class: 'bm-repeat-heading' }, [cardTitle, el('span', { class: 'bm-repeat-expand', 'aria-hidden': 'true', text: 'Modifier' })]));
                    card.append(cardControls);
                    const children = el('div', { class: 'bm-repeat-fields' });
                    (field.fields || []).forEach(function (child) { children.append(renderField(child, row, path + '.' + index, function () {
                        update(rows);
                        const updatedName = firstText && String(row[firstText.key] || '').trim();
                        cardTitle.textContent = updatedName ? (index + 1) + '. ' + updatedName : 'Élément ' + (index + 1);
                    })); });
                    card.append(children); list.append(card);
                });
                const add = button('+ Ajouter un élément', function () {
                    const row = {};
                    (field.fields || []).forEach(function (child) { row[child.key] = clone(child.default === undefined ? child.type === 'checkbox' ? false : '' : child.default); });
                    rows.push(row); update(rows); drawRows(rows.length - 1);
                }, 'bm-btn-secondary bm-add-item', { disabled: rows.length >= max });
                list.append(add, el('span', { class: 'bm-item-count', text: rows.length + ' / ' + max + ' éléments' }));
                if (focusIndex >= 0) {
                    const cards = list.querySelectorAll('.bm-repeat-card');
                    const target = cards[focusIndex] && cards[focusIndex].querySelector('input,textarea,select,button:not([disabled])');
                    if (target) target.focus();
                }
            }
            drawRows(); wrapper.append(list); return wrapper;
        }

        if (field.type === 'checkbox') {
            const input = el('input', Object.assign({}, common, { type: 'checkbox', checked: Boolean(value), on: { change: function () { update(input.checked); } } }));
            label.classList.add('bm-toggle-label');
            label.prepend(input, el('span', { class: 'bm-toggle-track', 'aria-hidden': 'true' }));
            wrapper.append(label);
        } else if (field.type === 'image' || field.type === 'video') {
            wrapper.append(label);
            const media = el('div', { class: 'bm-media' });
            let selection = typeof value === 'object' && value ? Object.assign({}, value) : { id: 0, url: '', alt: '' };
            function drawMedia() {
                media.replaceChildren();
                const url = safeUrl(selection.url);
                if (url) {
                    const visual = field.type === 'video' ? el('video', { src: url, controls: 'controls', preload: 'metadata', 'aria-label': field.label }) : el('img', { src: url, alt: selection.alt || '', loading: 'lazy' });
                    media.append(el('div', { class: 'bm-media-visual' }, visual));
                } else media.append(el('div', { class: 'bm-media-empty', 'aria-hidden': 'true', text: field.type === 'video' ? '▶' : '▧' }));
                const controls = el('div', { class: 'bm-media-details' });
                controls.append(button(url ? 'Changer ' + (field.type === 'video' ? 'la vidéo' : 'l’image') : 'Choisir ' + (field.type === 'video' ? 'une vidéo' : 'une image'), function () {
                    if (!window.wp || !window.wp.media) { setMessage('La médiathèque ne s’est pas chargée. Enregistrez votre brouillon et rechargez la page.', 'error', true); return; }
                    const picker = window.wp.media({ title: field.type === 'video' ? 'Choisir une vidéo' : 'Choisir une image', button: { text: 'Utiliser ce visuel' }, library: { type: field.type }, multiple: false });
                    picker.on('select', function () {
                        const selected = picker.state().get('selection').first().toJSON();
                        selection = { id: Number(selected.id) || 0, url: safeUrl(selected.url), alt: selected.alt || '' };
                        update(selection); drawMedia();
                        const changedButton = media.querySelector('button'); if (changedButton) changedButton.focus();
                    });
                    picker.open();
                }, 'bm-btn-secondary', { id: id, 'data-field': path }));
                if (url) {
                    controls.append(button('Retirer', function () { selection = { id: 0, url: '', alt: '' }; update(selection); drawMedia(); media.querySelector('button').focus(); }, 'bm-btn-text bm-remove'));
                    if (field.type === 'image') {
                        const altId = id + '-alt';
                        const alt = el('input', { type: 'text', id: altId, placeholder: 'Ex. : le produit posé sur une table', on: { input: function () { selection.alt = alt.value; update(selection); } } });
                        alt.value = selection.alt || '';
                        controls.append(el('label', { class: 'bm-media-alt-label', for: altId, text: 'Description de l’image' }), alt, el('p', { class: 'bm-field-help', text: 'Une phrase courte pour les personnes qui ne voient pas l’image.' }));
                    }
                } else controls.append(el('p', { class: 'bm-field-help', text: 'Sélectionnez un fichier dans votre médiathèque ou ajoutez-en un depuis votre appareil.' }));
                media.append(controls);
            }
            drawMedia(); wrapper.append(media);
        } else if (field.type === 'select') {
            const input = el('select', Object.assign({}, common, { on: { change: function () { update(input.value); } } }));
            const choices = Array.isArray(field.options) ? field.options.map(function (option) { return typeof option === 'object' ? option : { value: option, label: option }; }) : Object.entries(field.options || {}).map(function (entry) { return { value: entry[0], label: entry[1] }; });
            choices.forEach(function (choice) { input.append(el('option', { value: choice.value, text: choice.label })); });
            input.value = String(value); wrapper.append(label, input);
        } else if (field.type === 'rich') {
            const labelId = id + '-label';
            label.id = labelId;
            const input = el('div', Object.assign({}, common, { class: 'bm-rich-input', contenteditable: 'true', role: 'textbox', 'aria-multiline': 'true', 'aria-labelledby': labelId, tabindex: '0' }));
            input.append(richNodes(value));
            const changedRich = function () { input.removeAttribute('aria-invalid'); update(serializeRich(input)); };
            input.addEventListener('input', changedRich);
            function insertPlain(value) {
                input.focus();
                if (typeof document.execCommand === 'function') document.execCommand('insertText', false, value);
                else {
                    const selection = window.getSelection();
                    if (selection.rangeCount && input.contains(selection.anchorNode)) {
                        const range = selection.getRangeAt(0); range.deleteContents();
                        const node = document.createTextNode(value); range.insertNode(node); range.setStartAfter(node); range.collapse(true); selection.removeAllRanges(); selection.addRange(range);
                    } else input.append(document.createTextNode(value));
                }
                changedRich();
            }
            input.addEventListener('paste', function (event) { event.preventDefault(); insertPlain(event.clipboardData ? event.clipboardData.getData('text/plain') : ''); });
            input.addEventListener('drop', function (event) { event.preventDefault(); insertPlain(event.dataTransfer ? event.dataTransfer.getData('text/plain') : ''); });
            const toolbar = el('div', { class: 'bm-format-toolbar', role: 'group', 'aria-label': 'Mise en forme de ' + field.label });
            [['Gras', 'bold'], ['Italique', 'italic']].forEach(function (format) {
                const control = button(format[0], function () {
                    input.focus();
                    if (typeof document.execCommand === 'function') { document.execCommand('styleWithCSS', false, false); document.execCommand(format[1], false); changedRich(); }
                }, 'bm-format-button');
                control.addEventListener('mousedown', function (event) { event.preventDefault(); });
                toolbar.append(control);
            });
            toolbar.append(el('span', { text: 'Sélectionnez un mot pour le mettre en valeur.' }));
            wrapper.append(label, toolbar, input);
        } else if (field.type === 'textarea') {
            const input = el('textarea', Object.assign({}, common, { rows: 3, on: { input: function () { input.removeAttribute('aria-invalid'); update(input.value); } } }));
            input.value = value || ''; wrapper.append(label, input);
        } else if (field.type === 'color') {
            const colorValue = /^#[0-9a-f]{6}$/i.test(String(value)) ? value : '#65152f';
            const swatch = el('input', { type: 'color', value: colorValue, 'aria-label': 'Choisir la couleur pour ' + field.label });
            const input = el('input', Object.assign({}, common, { type: 'text', spellcheck: 'false', maxlength: '7', placeholder: '#65152f' }));
            input.value = value || '';
            swatch.addEventListener('input', function () { input.value = swatch.value; update(input.value); });
            input.addEventListener('input', function () { if (/^#[0-9a-f]{6}$/i.test(input.value)) swatch.value = input.value; update(input.value); });
            wrapper.append(label, el('div', { class: 'bm-color-control' }, [swatch, input]));
        } else {
            const isPrice = priceKeys.has(field.key);
            const input = el('input', Object.assign({}, common, { type: field.type === 'number' && !isPrice ? 'number' : 'text', inputmode: isPrice ? 'decimal' : field.type === 'number' ? 'numeric' : undefined, min: field.min, max: field.max, step: field.step || (field.type === 'number' ? '1' : undefined), on: { input: function () { input.removeAttribute('aria-invalid'); update(field.type === 'number' && !isPrice && input.value !== '' ? Number(input.value) : input.value); } } }));
            input.value = isPrice ? String(value).replace('.', ',') : value === null ? '' : String(value);
            wrapper.append(label);
            if (isPrice) wrapper.append(el('div', { class: 'bm-price-control' }, [input, el('span', { 'aria-hidden': 'true', text: '€' })]));
            else wrapper.append(input);
        }
        if (field.help) wrapper.append(el('p', { class: 'bm-field-help', id: helpId, text: field.help }));
        return wrapper;
    }

    function render() {
        if (!state.payload) return;
        root.replaceChildren(); root.className = 'bm-builder';
        const header = el('header', { class: 'bm-header' });
        const title = el('div', { class: 'bm-header-title' }, [el('span', { class: 'bm-eyebrow', text: 'BELMAINS · VOTRE ESPACE' }), el('h1', { text: 'Ma boutique' }), el('p', { text: 'Personnalisez votre page. Prévisualisez. Publiez quand elle est prête.' })]);
        const liveUrl = safeUrl(state.payload.live_url || boot.homeUrl);
        header.append(title);
        if (liveUrl) header.append(el('a', { class: 'bm-btn bm-btn-secondary bm-view-shop', href: liveUrl, target: '_blank', rel: 'noopener', text: 'Voir la boutique ↗' }));
        root.append(header);

        const picker = el('section', { class: 'bm-product-picker', 'aria-label': 'Choisir un produit' });
        productSelect = el('select', { id: 'bm-product-select', on: { change: async function () {
            const id = productSelect.value;
            productSelect.value = String(state.payload.id);
            if (Number(id) !== Number(state.payload.id) && await leaveDraft()) { state.section = 0; await loadProduct(id); }
        } } });
        state.products.forEach(function (product) {
            const statusLabel = product.active ? ' · Affiché sur la boutique' : product.status === 'draft' ? ' · En préparation' : ' · Autre produit';
            productSelect.append(el('option', { value: product.id, text: product.name + statusLabel + (product.has_draft && product.status !== 'draft' ? ' · Brouillon disponible' : '') }));
        });
        productSelect.value = String(state.payload.id);
        status = el('span', { role: 'status', 'aria-live': 'polite' });
        picker.append(el('div', { class: 'bm-product-choice' }, [el('label', { for: 'bm-product-select', text: 'Produit à personnaliser' }), productSelect]), el('div', { class: 'bm-product-meta' }, [status, button('+ Préparer un nouveau produit', duplicate, 'bm-btn-text', { disabled: state.conflict })]));
        root.append(picker);

        notice = el('div', { tabindex: '-1', 'aria-live': 'polite' }); root.append(notice);
        const layout = el('div', { class: 'bm-layout' });
        const sidebar = el('aside', { class: 'bm-sidebar' });
        const navigation = el('nav', { class: 'bm-section-nav', 'aria-label': 'Étapes de personnalisation' });
        state.payload.schema.forEach(function (section, index) {
            const navButton = button('', function () {
                state.section = index; render();
                const heading = root.querySelector('.bm-section-heading h2');
                if (heading) heading.focus({ preventScroll: true });
            }, 'bm-step' + (index === state.section ? ' is-current' : ''), { 'aria-current': index === state.section ? 'step' : undefined });
            navButton.append(el('span', { class: 'bm-step-number', 'aria-hidden': 'true', text: String(index + 1).padStart(2, '0') }), el('span', { text: sectionLabel(section) }));
            navigation.append(navButton);
        });
        const stock = state.payload.stock_quantity;
        const stockCard = el('div', { class: 'bm-stock-card' }, [el('span', { class: 'bm-eyebrow', text: 'STOCK DISPONIBLE' }), el('strong', { text: stock === null || stock === undefined ? 'À vérifier' : Number(stock).toLocaleString('fr-FR') + ' unités' }), el('p', { text: 'Le stock se gère séparément de la présentation du produit.' })]);
        const editUrl = safeUrl(state.payload.edit_url);
        if (editUrl) stockCard.append(el('a', { href: editUrl + '#inventory_product_data', target: '_blank', rel: 'noopener', text: 'Gérer le stock dans WooCommerce ↗' }));
        sidebar.append(navigation, stockCard); layout.append(sidebar);

        const section = currentSection();
        const main = el('main', { class: 'bm-editor-panel' });
        main.append(el('div', { class: 'bm-section-heading' }, [el('span', { class: 'bm-eyebrow', text: 'ÉTAPE ' + (state.section + 1) + ' SUR ' + state.payload.schema.length }), el('h2', { tabindex: '-1', text: sectionLabel(section) }), el('p', { text: section.description || 'Personnalisez cette partie de votre page.' })]));
        const fields = el('div', { class: 'bm-editor-fields' });
        section.fields.forEach(function (field) { fields.append(renderField(field, state.values, '', markDirty)); });
        main.append(fields);
        const navigationFooter = el('div', { class: 'bm-step-footer' });
        if (state.section > 0) navigationFooter.append(button('← Étape précédente', function () { state.section--; render(); root.querySelector('.bm-section-heading h2').focus(); }, 'bm-btn-text'));
        else navigationFooter.append(el('span'));
        if (state.section < state.payload.schema.length - 1) navigationFooter.append(button('Continuer : ' + sectionLabel(state.payload.schema[state.section + 1]) + ' →', function () { state.section++; render(); root.querySelector('.bm-section-heading h2').focus(); }, 'bm-btn-secondary'));
        else navigationFooter.append(button('Prévisualiser ma page →', preview, 'bm-btn-secondary', { disabled: state.conflict }));
        main.append(navigationFooter); layout.append(main); root.append(layout);
        const bottom = el('div', { class: 'bm-secondary-actions' });
        bottom.append(el('p', { text: 'Une modification n’apparaît sur la boutique qu’après avoir cliqué sur Publier.' }));
        if (state.payload.has_draft && state.payload.status === 'publish') bottom.append(button('Revenir à la version publiée', discard, 'bm-btn-text', { disabled: state.conflict }));
        root.append(bottom);
        actions = el('div', { class: 'bm-action-bar', 'aria-label': 'Enregistrer et publier' }); root.append(actions);
        refreshNotice(); refreshStatus(); refreshActions();
        const activeStep = navigation.querySelector('.is-current');
        if (activeStep && navigation.scrollWidth > navigation.clientWidth) navigation.scrollLeft = activeStep.offsetLeft - navigation.offsetLeft - Math.max(0, (navigation.clientWidth - activeStep.offsetWidth) / 2);
        if (state.busy) setBusy(true);
    }

    window.addEventListener('beforeunload', function (event) { if (state.dirty) { event.preventDefault(); event.returnValue = ''; } });

    async function init() {
        root.className = 'bm-builder';
        root.append(el('div', { class: 'bm-loading', role: 'status', text: 'Ouverture de votre boutique…' }));
        try {
            const listing = await request('/products');
            state.products = Array.isArray(listing.items) ? listing.items : [];
            if (!state.products.length) {
                root.replaceChildren(el('div', { class: 'bm-empty-state' }, [el('span', { class: 'bm-eyebrow', text: 'MA BOUTIQUE' }), el('h1', { text: 'Préparons votre premier produit' }), el('p', { text: 'Créez un produit simple dans WooCommerce. Vous pourrez ensuite revenir ici pour personnaliser sa page.' }), el('a', { class: 'bm-btn bm-btn-primary', href: adminUrl('post-new.php?post_type=product'), text: 'Créer un produit dans WooCommerce' })]));
                return;
            }
            const selected = state.products.find(function (product) { return Number(product.id) === Number(listing.active_id || boot.activeId); }) || state.products[0];
            await loadProduct(selected.id);
            if (!state.payload) throw new Error(state.message || 'La fiche n’a pas pu être ouverte.');
        } catch (error) {
            if (state.payload) { fail(error); return; }
            root.replaceChildren(el('div', { class: 'bm-empty-state', role: 'alert' }, [el('h1', { text: 'La boutique n’a pas pu être ouverte' }), el('p', { text: error.message }), button('Réessayer', function () { root.replaceChildren(); init(); }, 'bm-btn-primary')]));
        }
    }

    init();
}());
