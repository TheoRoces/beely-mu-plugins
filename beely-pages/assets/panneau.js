// /inc/js/beely-pages-panel.js
(function () {
    const cfg = window.BeelyPages || {};
    const ajaxUrl = cfg.ajaxUrl;
    const nonce = cfg.nonce;
    const peutSupprimer = cfg.peutSupprimer !== false;
    const peutDefinirAccueil = cfg.peutDefinirAccueil !== false;

    let enableSlugSync = false;
    let isSlugValid = true;
    let hasLoaded = false;
    let isProcessing = false;
    let frontPageId = 0;

    // Flag changements non sauvegardés
    let hasUnsavedChanges = false;

    // ----- ICONS -----
    const ICON_FOLDER_CLOSED = `<svg class="icon-folder-closed" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12.75V12A2.25 2.25 0 0 1 4.5 9.75h15A2.25 2.25 0 0 1 21.75 12v.75m-8.69-6.44-2.12-2.12a1.5 1.5 0 0 0-1.061-.44H4.5A2.25 2.25 0 0 0 2.25 6v12a2.25 2.25 0 0 0 2.25 2.25h15A2.25 2.25 0 0 0 21.75 18V9a2.25 2.25 0 0 0-2.25-2.25h-5.379a1.5 1.5 0 0 1-1.06-.44Z" /></svg>`;
    const ICON_FOLDER_OPEN = `<svg class="icon-folder-open" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 9.776c.112-.017.227-.026.344-.026h15.812c.117 0 .232.009.344.026m-16.5 0a2.25 2.25 0 0 0-1.883 2.542l.857 6a2.25 2.25 0 0 0 2.227 1.932H19.05a2.25 2.25 0 0 0 2.227-1.932l.857-6a2.25 2.25 0 0 0-1.883-2.542m-16.5 0V6A2.25 2.25 0 0 1 6 3.75h3.879a1.5 1.5 0 0 1 1.06.44l2.122 2.12a1.5 1.5 0 0 0 1.06.44H18A2.25 2.25 0 0 1 20.25 9v.776" /></svg>`;
    const ICON_PAGE = `<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" /></svg>`;
    const ICON_HOME = `<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m2.25 12 8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25" /></svg>`;
    const ICON_BTN_MAIN = `<svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" class="bricks-svg"><path d="M17.625 23.25h-13.5a1.5 1.5 0 0 1-1.5-1.5V5.625" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"></path><path d="M21.375 18.159A1.8 1.8 0 0 1 19.625 20H7.375a1.8 1.8 0 0 1-1.75-1.841V2.591A1.8 1.8 0 0 1 7.375.75h8.9a1.7 1.7 0 0 1 1.238.539l3.349 3.524a1.9 1.9 0 0 1 .513 1.3Z" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"></path></svg>`;

    // --- SAFETY NET (Refresh/Close Tab) ---
    window.addEventListener('beforeunload', (e) => {
        if (hasUnsavedChanges) {
            e.preventDefault();
            e.returnValue = '';
        }
    });

    // ----- UTILS -----
    function getStoredExpanded() { try { return JSON.parse(localStorage.getItem('beely_expanded_folders')) || []; } catch (e) { return []; } }
    function saveExpandedState() {
        const openIds = Array.from(document.querySelectorAll('#beely-pages-list li.is-open')).map(li => li.dataset.id);
        localStorage.setItem('beely_expanded_folders', JSON.stringify(openIds));
    }
    function addExpandedFolder(id) {
        const current = getStoredExpanded();
        if (!current.includes(String(id))) {
            current.push(String(id));
            localStorage.setItem('beely_expanded_folders', JSON.stringify(current));
        }
    }

    function showToast(msg, type = 'success') {
        let container = document.getElementById('beely-toast-container');
        if (!container) { container = document.createElement('div'); container.id = 'beely-toast-container'; document.body.appendChild(container); }
        const toast = document.createElement('div'); toast.className = `beely-toast ${type}`; toast.textContent = msg;
        container.appendChild(toast);
        setTimeout(() => { toast.style.opacity = '0'; setTimeout(() => toast.remove(), 300); }, 3000);
    }

    function showConfirm(title, message, isDanger = false) {
        return new Promise((resolve) => {
            const overlay = document.createElement('div'); overlay.id = 'beely-confirm-overlay';
            overlay.innerHTML = `<div class="beely-confirm-box"><h3>${title}</h3><p>${message}</p><div class="beely-confirm-actions"><button class="beely-confirm-cancel">Annuler</button><button class="beely-confirm-ok ${isDanger ? 'danger' : ''}">Confirmer</button></div></div>`;
            document.body.appendChild(overlay);

            const cleanup = () => { overlay.remove(); document.removeEventListener('keydown', handleKey); };

            const handleKey = (e) => {
                if (e.key === 'Enter') { e.preventDefault(); e.stopPropagation(); cleanup(); resolve(true); }
                else if (e.key === 'Escape') { e.preventDefault(); cleanup(); resolve(false); }
            };
            document.addEventListener('keydown', handleKey);

            overlay.querySelector('.beely-confirm-cancel').onclick = () => { cleanup(); resolve(false); };
            overlay.querySelector('.beely-confirm-ok').onclick = () => { cleanup(); resolve(true); };
            overlay.onclick = (e) => { if (e.target === overlay) { cleanup(); resolve(false); } };

            setTimeout(() => overlay.querySelector('.beely-confirm-ok').focus(), 50);
        });
    }

    // --- POPUP UNSAVED CHANGES ---
    function showUnsavedWarning(onDiscard, onSave) {
        const overlay = document.createElement('div'); overlay.id = 'beely-unsaved-overlay';
        overlay.innerHTML = `
            <div class="beely-confirm-box unsaved-box">
                <h3>Modifications non sauvegardées</h3>
                <p>Vous avez des modifications en attente. Que voulez-vous faire ?</p>
                <div class="beely-confirm-actions vertical-actions">
                    <button class="beely-confirm-ok primary" id="unsaved-save">Sauvegarder & Quitter</button>
                    <button class="beely-confirm-cancel" id="unsaved-discard">Abandonner les modifications</button>
                    <button class="beely-confirm-cancel plain" id="unsaved-stay">Continuer l'édition</button>
                </div>
            </div>`;
        document.body.appendChild(overlay);

        const cleanup = () => overlay.remove();

        // 1. Sauvegarder
        overlay.querySelector('#unsaved-save').onclick = async () => {
            // On sauvegarde et on ferme APRES la sauvegarde réussie
            cleanup();
            await savePage(null, true);
            if (onSave) onSave();
        };

        // 2. Abandonner
        overlay.querySelector('#unsaved-discard').onclick = () => {
            cleanup();
            hasUnsavedChanges = false;
            if (onDiscard) onDiscard();
        };

        // 3. Rester
        overlay.querySelector('#unsaved-stay').onclick = () => {
            cleanup();
        };
    }

    function checkUnsavedChanges(nextAction) {
        const popup = document.getElementById('beely-page-popup');
        const isPopupVisible = popup && popup.style.display === 'block';

        if (hasUnsavedChanges && isPopupVisible) {
            showUnsavedWarning(() => {
                // On Discard callback
                nextAction();
            }, () => {
                // On Save callback 
                nextAction();
            });
        } else {
            nextAction();
        }
    }

    function slugify(text) { return text.toString().toLowerCase().trim().replace(/\s+/g, '-').replace(/[^\w\-]+/g, '').replace(/\-\-+/g, '-'); }

    function injectOrReplaceButton() {
        const toolbar = document.querySelector('#bricks-toolbar ul.group-wrapper.start');
        if (!toolbar) return;

        let myBtn = document.getElementById('beely-pages-button');
        if (myBtn && toolbar.contains(myBtn)) return;

        if (!myBtn) {
            myBtn = document.createElement('button'); myBtn.id = 'beely-pages-button'; myBtn.type = 'button'; myBtn.innerHTML = ICON_BTN_MAIN;
            myBtn.setAttribute('data-balloon', 'Gérer les pages'); myBtn.setAttribute('data-balloon-pos', 'bottom');
            myBtn.addEventListener('click', togglePanel);
        }

        const nativeLi = toolbar.querySelector('li.pages');
        if (nativeLi) {
            toolbar.insertBefore(myBtn, nativeLi);
        } else {
            const liClasses = toolbar.querySelector('li.classes');
            if (liClasses) liClasses.insertAdjacentElement('afterend', myBtn); else toolbar.appendChild(myBtn);
        }
    }

    setInterval(injectOrReplaceButton, 1000);
    setTimeout(injectOrReplaceButton, 500);

    function highlightEditingItem(id) {
        const allItems = document.querySelectorAll('#beely-pages-list li');
        allItems.forEach(li => li.classList.remove('is-editing'));
        if (id) {
            const target = document.querySelector(`#beely-pages-list li[data-id="${id}"]`);
            if (target) target.classList.add('is-editing');
        }
    }

    // ----- UI PANEL -----
    function ensurePanel() {
        let panel = document.getElementById('beely-pages-panel');
        if (panel) return panel;

        const overlay = document.createElement('div');
        overlay.id = 'beely-panel-overlay';
        overlay.addEventListener('click', () => {
            if (panel.classList.contains('active')) checkUnsavedChanges(togglePanel);
        });
        document.body.appendChild(overlay);

        panel = document.createElement('div'); panel.id = 'beely-pages-panel';
        panel.innerHTML = `
            <div class="panel-header"><span>ARBORESCENCE</span><div class="panel-actions" style="display:flex;gap:4px;"><button class="btn" id="beely-create" title="Créer"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width:16px;height:16px;"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg></button><button class="btn" id="beely-refresh" title="Recharger"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width:16px;height:16px;"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" /></svg></button><button class="btn" id="beely-close" title="Fermer"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width:16px;height:16px;"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg></button></div></div>
            <input id="beely-pages-search" placeholder="Filtrer les pages..."><ul id="beely-pages-list"></ul>
            <div id="beely-page-popup">
                <form id="beely-page-form" novalidate>
                    <div style="display:flex;gap:6px;justify-content:flex-end;padding:15px 20px;;border-bottom:1px solid #3d4752;margin-bottom:10px;">
                        <button type="button" class="btn primary-action" id="beely-popup-sethome" title="Définir comme page d'accueil" style="display:none; margin-right:auto;">
                           <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width:16px;height:16px;"><path stroke-linecap="round" stroke-linejoin="round" d="m2.25 12 8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25" /></svg>
                        </button>
                        <button type="button" class="btn" id="beely-popup-duplicate" title="Dupliquer">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width:16px;height:16px;"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 17.25v3.375c0 .621-.504 1.125-1.125 1.125h-9.75a1.125 1.125 0 0 1-1.125-1.125V7.875c0-.621.504-1.125 1.125-1.125H6.75a9.06 9.06 0 0 1 1.5.124m7.5 10.376h3.375c.621 0 1.125-.504 1.125-1.125V11.25c0-4.46-3.243-8.161-7.5-8.876a9.06 9.06 0 0 0-1.5-.124H9.375c-.621 0-1.125.504-1.125 1.125v3.5m7.5 10.375H9.375a1.125 1.125 0 0 1-1.125-1.125v-9.25m12 6.625v-1.875a3.375 3.375 0 0 0-3.375-3.375h-1.5a1.125 1.125 0 0 1-1.125-1.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H9.75" /></svg>
                        </button>
                        <button type="button" class="btn danger" id="beely-popup-delete" title="Corbeille"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width:16px;height:16px;"><path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" /></svg></button>
                        <button type="button" class="btn" id="beely-popup-cancel" title="Fermer"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width:16px;height:16px;"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg></button>
                        <button type="submit" class="btn primary" id="beely-popup-save" title="Enregistrer"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width:16px;height:16px;"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" /></svg></button>
                    </div>
                    
                    <div style="height: calc(100vh - 120px); overflow-y: auto; padding: 0px 20px 20px 20px;">
                        <input type="hidden" name="page_id"><label>Nom <span style="color:#f55">*</span></label><input name="post_title" required><label>Slug <span style="color:#f55">*</span></label><input name="post_name" required><div id="beely-slug-feedback"></div>
                        
                        <!-- PARENT SELECT CONTAINER -->
                        <div id="beely-parent-container">
                            <label>Parent</label>
                            <select name="post_parent"></select>
                        </div>

                        <label>État</label><select name="post_status" required><option value="publish" selected>Publier</option><option value="draft">Brouillon</option></select>
                        
                        <label class="beely-checkbox-wrapper">
                            <input type="checkbox" name="noindex" value="1">
                            <span class="beely-checkbox-fake"></span>
                            <span class="beely-label-text">Désindexer cette page (Noindex)</span>
                        </label>

                        <label>Image à la une</label><div class="beely-image-wrapper" id="beely-image-wrapper"><div class="beely-image-preview"></div><div class="beely-image-placeholder"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 0 0 1.5-1.5V6a1.5 1.5 0 0 0-1.5-1.5H3.75A1.5 1.5 0 0 0 2.25 6v12a1.5 1.5 0 0 0 1.5 1.5Zm10.5-11.25h.008v.008h-.008V8.25Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" /></svg><span>Ajouter une image</span></div><div id="beely-remove-image"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg></div><input type="hidden" name="_thumbnail_id" id="beely-thumbnail-id"></div>
                        
                        <label>Titre SEO <span class="beely-compteur" data-pour="seo_title"></span></label>
                        <input name="seo_title" maxlength="120">
                        <p class="beely-aide">Vide : le modèle du site s’applique.</p>

                        <label>Méta description <span class="beely-compteur" data-pour="seo_desc"></span></label>
                        <textarea name="seo_desc" maxlength="320"></textarea>

                        <label>URL canonique</label>
                        <input name="seo_canonical" type="url" placeholder="https://…">
                        <p class="beely-aide">À ne renseigner que si cette page reprend le contenu d’une autre.</p>
                    </div>
                </form>
            </div>`;
        document.body.appendChild(panel);

        // Event Listeners
        panel.querySelector('#beely-close').onclick = () => { checkUnsavedChanges(togglePanel); };
        panel.querySelector('#beely-refresh').onclick = () => { loadPages(true); };
        panel.querySelector('#beely-create').onclick = () => { checkUnsavedChanges(openCreatePopup); };

        panel.querySelector('#beely-pages-search').oninput = (e) => {
            const q = e.target.value.toLowerCase();
            panel.querySelectorAll('#beely-pages-list li').forEach(li => li.style.display = li.textContent.toLowerCase().includes(q) ? 'flex' : 'none');
        };
        const form = document.getElementById('beely-page-form');
        form.onsubmit = (e) => savePage(e, true);

        // DETECTION CHANGEMENTS
        form.addEventListener('input', () => { hasUnsavedChanges = true; });

        document.getElementById('beely-popup-cancel').onclick = () => { checkUnsavedChanges(closePopup); };
        document.getElementById('beely-popup-duplicate').onclick = duplicatePage;
        document.getElementById('beely-popup-delete').onclick = deletePage;
        document.getElementById('beely-popup-sethome').onclick = setHomepage;

        const titleInput = form.querySelector('[name=post_title]');
        const slugInput = form.querySelector('[name=post_name]');
        const parentInput = form.querySelector('[name=post_parent]');

        titleInput.addEventListener('input', () => { if (enableSlugSync) { slugInput.value = slugify(titleInput.value); checkSlugAvailability(); } });
        slugInput.addEventListener('input', () => { enableSlugSync = false; debounce(checkSlugAvailability, 500)(); });
        parentInput.addEventListener('change', () => { checkSlugAvailability(); hasUnsavedChanges = true; });

        document.getElementById('beely-image-wrapper').addEventListener('click', handleImageUpload);
        document.getElementById('beely-remove-image').addEventListener('click', (e) => { e.stopPropagation(); removeImage(); });

        return panel;
    }

    let mediaFrame;
    function handleImageUpload(e) {
        e.preventDefault();
        if (mediaFrame) { mediaFrame.open(); return; }
        if (typeof wp === 'undefined' || !wp.media) { showToast("Media Library non dispo", "error"); return; }
        mediaFrame = wp.media({ title: 'Image à la une', button: { text: 'Choisir' }, multiple: false });
        mediaFrame.on('select', () => {
            const att = mediaFrame.state().get('selection').first().toJSON();
            setImage(att.id, att.sizes?.medium?.url || att.url);
            hasUnsavedChanges = true;
        });
        mediaFrame.open();
    }
    function setImage(id, url, silent = false) {
        const wrapper = document.getElementById('beely-image-wrapper');
        document.getElementById('beely-thumbnail-id').value = id;
        wrapper.querySelector('.beely-image-preview').innerHTML = `<img src="${url}">`;
        wrapper.classList.add('has-image');
        if (!silent) hasUnsavedChanges = true;
    }
    function removeImage(silent = false) {
        const wrapper = document.getElementById('beely-image-wrapper');
        document.getElementById('beely-thumbnail-id').value = '';
        wrapper.querySelector('.beely-image-preview').innerHTML = '';
        wrapper.classList.remove('has-image');
        if (!silent) hasUnsavedChanges = true;
    }

    function togglePanel() {
        const panel = ensurePanel();
        const overlay = document.getElementById('beely-panel-overlay');
        const isActive = panel.classList.toggle('active');
        overlay.classList.toggle('active', isActive);
        document.getElementById('beely-pages-button')?.classList.toggle('active', isActive);

        if (isActive) {
            if (!hasLoaded || document.getElementById('beely-pages-list').innerHTML === '') {
                loadPages();
            }
            const otherTriggers = document.querySelectorAll('#bricks-toolbar ul.group-wrapper > li.elements.active, #bricks-toolbar ul.group-wrapper > li.structure.active, #bricks-toolbar ul.group-wrapper > li.classes.active, #bricks-toolbar ul.group-wrapper > li.settings.active');
            otherTriggers.forEach(li => li.click());
        } else {
            closePopup();
        }
    }

    // ----- RENDER -----
    let lastPagesCache = [];
    async function loadPages(force = false) {
        if (hasLoaded && !force) return;

        const list = document.getElementById('beely-pages-list');
        if (!list) return;
        if (!list.hasChildNodes()) list.innerHTML = '<li style="justify-content:center;color:#666;">Chargement...</li>';

        try {
            const resp = await fetch(ajaxUrl, { method: 'POST', body: new URLSearchParams({ action: 'beely_pages_lire', nonce }) });
            const data = await resp.json();
            if (data.success) {
                lastPagesCache = data.data.pages || [];
                frontPageId = parseInt(data.data.front_page_id || 0);
                renderTree(list, lastPagesCache);
                hasLoaded = true;
            }
        } catch (e) {
            list.innerHTML = '<li style="color:#d33">Erreur de chargement</li>';
        }
    }

    function renderTree(list, pages) {
        const map = {};
        const parentMap = {};
        pages.forEach(p => { map[p.id] = { ...p, children: [] }; parentMap[p.id] = parseInt(p.parent) || 0; });
        let roots = [];
        pages.forEach(p => { if (p.parent && map[p.parent]) map[p.parent].children.push(map[p.id]); else roots.push(map[p.id]); });

        const frontIndex = roots.findIndex(r => r.id === frontPageId);
        if (frontIndex > -1) {
            const frontNode = roots.splice(frontIndex, 1)[0];
            roots.unshift(frontNode);
        }

        const fragment = document.createDocumentFragment();

        const qs = new URLSearchParams(window.location.search);
        let activeId = parseInt(qs.get('post') || '0', 10);

        if (!activeId && window.bricksData && window.bricksData.postId) {
            activeId = parseInt(window.bricksData.postId, 10);
        }

        const pathClean = window.location.pathname.replace(/^\/|\/$/g, '');
        const pathSegments = pathClean.split('/');
        const currentSlug = pathSegments.length > 0 ? pathSegments[pathSegments.length - 1] : '';

        const storedExpanded = new Set(getStoredExpanded());

        let curr = activeId;
        while (curr && parentMap[curr]) { storedExpanded.add(String(parentMap[curr])); curr = parentMap[curr]; }

        const renderNode = (node, depth, ancestors, isLastChild) => {
            const li = document.createElement('li');
            li.dataset.id = node.id; li.dataset.parent = node.parent; li.dataset.depth = depth;
            li.className = `depth-${Math.min(depth, 6)}`;

            if (isLastChild) li.classList.add('is-last-child');
            if (node.children.length > 0) li.classList.add('has-children');

            ancestors.forEach((isActive, index) => {
                if (isActive) {
                    const guide = document.createElement('span');
                    guide.className = 'beely-tree-guide';
                    guide.style.left = (19 + (index * 20)) + 'px';
                    li.appendChild(guide);
                }
            });

            li.setAttribute('draggable', 'true');

            const isIdMatch = (activeId && node.id == activeId);
            const isSlugMatch = (!activeId && node.slug === currentSlug);

            if (isIdMatch || isSlugMatch) {
                li.classList.add('page-active');
                if (!activeId) activeId = node.id;
            }

            if (storedExpanded.has(String(node.id))) li.classList.add('is-open');

            const iconCol = document.createElement('span');
            iconCol.className = 'beely-icon-col';
            if (node.children.length > 0) {
                iconCol.innerHTML = ICON_FOLDER_CLOSED + ICON_FOLDER_OPEN;
                iconCol.onclick = (e) => { e.stopPropagation(); toggleChildren(li); };
            } else {
                if (node.id === frontPageId) {
                    iconCol.innerHTML = ICON_HOME;
                    iconCol.style.color = '#1e69fe';
                } else {
                    iconCol.innerHTML = ICON_PAGE;
                }
            }

            const title = document.createElement('span'); title.className = 'page-title';
            title.textContent = node.title || '(Sans titre)';
            if (node.status !== 'publish') title.textContent += ` (${node.status})`;

            const menu = document.createElement('button'); menu.className = 'beely-menu-btn';
            menu.innerHTML = `<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width:16px;height:16px;"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 12a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0ZM12.75 12a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0ZM18.75 12a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0ZM18.75 12a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Z" /></svg>`;
            // Modification du listener pour utiliser checkUnsavedChanges
            menu.onclick = (e) => {
                e.stopPropagation();
                checkUnsavedChanges(() => openEditPopup(node.id, node));
            };

            li.append(iconCol, title, menu);

            li.onclick = (e) => {
                if (!li.classList.contains('just-dragged')) {
                    checkUnsavedChanges(() => {
                        let targetUrl = node.permalink;
                        if (targetUrl) {
                            const separator = targetUrl.includes('?') ? '&' : '?';
                            window.location.href = targetUrl + separator + 'bricks=run';
                        } else {
                            window.location.href = `?post=${node.id}&bricks=run`;
                        }
                    });
                }
            };

            fragment.appendChild(li);

            if (node.children.length) {
                node.children.forEach((c, index) => {
                    const isLast = index === node.children.length - 1;
                    const childAncestors = [...ancestors, !isLastChild];
                    renderNode(c, depth + 1, childAncestors, isLast);
                });
            }
        };

        roots.forEach((n, index) => {
            const isLast = index === roots.length - 1;
            renderNode(n, 0, [], isLast);
        });

        list.innerHTML = '';
        list.appendChild(fragment);
        applyInitialVisibility(list);
        activateDnD(list);
    }

    function applyInitialVisibility(list) {
        const items = Array.from(list.querySelectorAll('li'));
        items.forEach(li => {
            if (!li.classList.contains('is-open')) {
                const base = parseInt(li.dataset.depth);
                let next = li.nextElementSibling;
                while (next && parseInt(next.dataset.depth) > base) {
                    next.style.display = 'none';
                    next = next.nextElementSibling;
                }
            }
        });
    }

    function toggleChildren(li) {
        li.classList.toggle('is-open');
        saveExpandedState();
        const base = parseInt(li.dataset.depth);
        let next = li.nextElementSibling;
        const isClosed = !li.classList.contains('is-open');
        while (next && parseInt(next.dataset.depth) > base) {
            next.style.display = isClosed ? 'none' : 'flex';
            if (!isClosed && parseInt(next.dataset.depth) === base + 1) next.style.display = 'flex';
            next = next.nextElementSibling;
        }
        if (!isClosed) applyInitialVisibility(document.getElementById('beely-pages-list'));
    }

    // ----- DRAG & DROP -----
    let dropLine, dragState, rafId;
    let listRectCache = null;

    function ensureDropLine() {
        if (!dropLine) { dropLine = document.createElement('div'); dropLine.id = 'beely-drop-line'; document.body.appendChild(dropLine); }
        return dropLine;
    }

    function activateDnD(list) {
        const line = ensureDropLine();
        const items = list.querySelectorAll('li');

        items.forEach(li => {
            li.ondragstart = (e) => {
                e.stopPropagation(); e.dataTransfer.effectAllowed = 'move';
                e.dataTransfer.setData('text/plain', li.dataset.id);
                listRectCache = list.getBoundingClientRect();

                const oldParentId = li.dataset.parent;

                dragState = {
                    el: li,
                    id: li.dataset.id,
                    baseDepth: parseInt(li.dataset.depth),
                    children: getChildrenBlock(li),
                    oldParentId: oldParentId
                };
                li.classList.add('dragging'); dragState.children.forEach(c => c.classList.add('dragging'));
            };
            li.ondragend = () => {
                li.removeAttribute('draggable'); setTimeout(() => li.setAttribute('draggable', 'true'), 50);
                li.classList.remove('dragging'); if (dragState) dragState.children.forEach(c => c.classList.remove('dragging'));
                line.style.display = 'none'; dragState = null;
                li.classList.add('just-dragged'); setTimeout(() => li.classList.remove('just-dragged'), 200);
            };
        });

        list.ondragover = (e) => {
            e.preventDefault();
            if (!dragState) return;
            if (rafId) return;
            rafId = requestAnimationFrame(() => { handleDragOver(e, list, line); rafId = null; });
        };

        list.ondrop = async (e) => {
            e.preventDefault(); if (!dragState || !dragState.dropData) return;
            line.style.display = 'none';
            const { anchorId, mode, depth } = dragState.dropData;

            const oldParentId = dragState.oldParentId;

            const block = [dragState.el, ...dragState.children];
            block.forEach(el => el.remove());
            const fresh = [...list.querySelectorAll('li')];
            const anchor = fresh.find(el => el.dataset.id === anchorId);
            const idx = fresh.indexOf(anchor);

            dragState.el.dataset.depth = depth;
            dragState.el.className = `depth-${Math.min(depth, 6)}`;
            const diff = depth - dragState.baseDepth;
            dragState.children.forEach(c => {
                const nd = parseInt(c.dataset.depth) + diff; c.dataset.depth = nd; c.className = `depth-${Math.min(nd, 6)}`;
            });

            let newParentId = 0;

            if (anchor) {
                if (mode === 'after') {
                    if (idx === fresh.length - 1) block.forEach(el => list.appendChild(el));
                    else block.forEach(el => list.insertBefore(el, fresh[idx + 1]));
                    addExpandedFolder(anchor.dataset.id);

                    const anchorDepth = parseInt(anchor.dataset.depth);
                    if (depth > anchorDepth) {
                        newParentId = anchor.dataset.id;
                    } else {
                        newParentId = anchor.dataset.parent;
                    }

                } else {
                    block.forEach(el => list.insertBefore(el, anchor));
                    newParentId = anchor.dataset.parent;
                }
            } else {
                block.forEach(el => list.appendChild(el));
                newParentId = 0;
            }

            updateVisualIconState(oldParentId, list);
            updateVisualIconState(newParentId, list);

            const newOrder = []; const depthMap = { '-1': 0 };
            list.querySelectorAll('li').forEach(li => {
                const d = parseInt(li.dataset.depth); const id = li.dataset.id;
                depthMap[d] = id;
                const pId = depthMap[d - 1] || 0;
                newOrder.push({ id, parent: pId });
                li.dataset.parent = pId;
            });

            await saveOrder(newOrder);
            await loadPages(true);
        };
    }

    function updateVisualIconState(parentId, list) {
        if (!parentId || parentId == 0) return;

        const parentLi = list.querySelector(`li[data-id="${parentId}"]`);
        if (!parentLi) return;

        const parentDepth = parseInt(parentLi.dataset.depth);
        const nextLi = parentLi.nextElementSibling;

        const hasChildren = (nextLi && parseInt(nextLi.dataset.depth) > parentDepth);

        const iconCol = parentLi.querySelector('.beely-icon-col');

        if (hasChildren) {
            if (!parentLi.classList.contains('has-children')) {
                parentLi.classList.add('has-children');
                iconCol.innerHTML = ICON_FOLDER_CLOSED + ICON_FOLDER_OPEN;
                iconCol.onclick = (e) => { e.stopPropagation(); toggleChildren(parentLi); };
            }
        } else {
            if (parentLi.classList.contains('has-children')) {
                parentLi.classList.remove('has-children', 'is-open');
                iconCol.innerHTML = ICON_PAGE;
                iconCol.onclick = null;
            }
        }
    }

    function handleDragOver(e, list, line) {
        if (!dragState) return;
        const target = e.target.closest('li');
        if (!target || target === dragState.el || dragState.children.includes(target)) { line.style.display = 'none'; return; }

        const rect = target.getBoundingClientRect();
        const listLeft = listRectCache ? listRectCache.left : list.getBoundingClientRect().left;
        const offsetX = e.clientX - listLeft;
        let proposedDepth = Math.floor((offsetX - 8) / 20); if (proposedDepth < 0) proposedDepth = 0;
        const targetDepth = parseInt(target.dataset.depth);
        const relY = e.clientY - rect.top; const isBottom = relY > (rect.height / 2);

        let mode = 'before';
        if (proposedDepth > targetDepth && target !== list.firstElementChild) mode = 'after';
        else if (isBottom) mode = 'after';
        let maxDepth = (mode === 'after') ? targetDepth + 1 : targetDepth;
        if (proposedDepth > maxDepth) proposedDepth = maxDepth;

        // --- REGLES DRAG & DROP HOME ---
        // 1. Si on drag la Home, elle doit rester à depth 0
        if (dragState.id == frontPageId) {
            proposedDepth = 0;
            maxDepth = 0;
        }

        // 2. Si on drag sur la Home (target == Home), on ne peut pas devenir son enfant
        // On force le mode à 'after' ou 'before' du même niveau (donc maxDepth = targetDepth)
        if (target.dataset.id == frontPageId) {
            maxDepth = targetDepth; // Bloque l'indentation sous la home
            if (proposedDepth > maxDepth) proposedDepth = maxDepth;
        }
        // -------------------------------

        line.style.display = 'block';
        const topY = (mode === 'after' ? rect.bottom : rect.top);
        const indentPx = 20;
        const padding = 8;

        const absoluteX = listLeft + (proposedDepth * indentPx) + padding;

        line.style.transform = `translate3d(${absoluteX}px, ${topY}px, 0)`;
        line.style.width = (rect.width - (proposedDepth * indentPx)) + 'px';

        dragState.dropData = { anchorId: target.dataset.id, mode, depth: proposedDepth };
    }

    function getChildrenBlock(li) {
        const children = []; const base = parseInt(li.dataset.depth); let next = li.nextElementSibling;
        while (next) { if (parseInt(next.dataset.depth) > base) { children.push(next); next = next.nextElementSibling; } else break; }
        return children;
    }
    async function saveOrder(order) { return fetch(ajaxUrl, { method: 'POST', body: new URLSearchParams({ action: 'beely_pages_ordonner', nonce, order: JSON.stringify(order) }) }); }

    function openCreatePopup() {
        highlightEditingItem(null);
        hasUnsavedChanges = false; // Reset

        const popup = document.getElementById('beely-page-popup'); const form = document.getElementById('beely-page-form');
        form.reset();

        form.querySelector('[name=page_id]').value = '';
        form.querySelector('#beely-popup-duplicate').style.display = 'none';
        form.querySelector('#beely-popup-delete').style.display = 'none';
        form.querySelector('#beely-popup-sethome').style.display = 'none';
        form.querySelector('[name=post_status]').value = 'publish';

        // Activer le selecteur de status pour création
        const statusSel = form.querySelector('[name=post_status]');
        statusSel.disabled = false;

        // Activer le slug pour création
        const slugInput = form.querySelector('[name=post_name]');
        slugInput.disabled = false;

        form.querySelector('[name=noindex]').checked = false;

        const feedback = document.getElementById('beely-slug-feedback');
        const saveBtn = document.getElementById('beely-popup-save');
        feedback.style.display = 'none';
        isSlugValid = true;
        saveBtn.disabled = false;
        saveBtn.style.opacity = '1';

        removeImage(true); // SILENT REMOVE

        // Parent select visible pour création
        const parentContainer = document.getElementById('beely-parent-container');
        if (parentContainer) parentContainer.style.display = 'block';

        fillParentSelect(form, 0); enableSlugSync = true; popup.style.display = 'block';

        // IMPORTANT: RESET FLAG AGAIN AFTER DOM UPDATES
        setTimeout(() => { hasUnsavedChanges = false; }, 50);
    }
    function openEditPopup(id, data) {
        highlightEditingItem(id);
        hasUnsavedChanges = false; // Reset au chargement de l'édition

        const fullData = lastPagesCache.find(p => p.id == id) || data;
        const popup = document.getElementById('beely-page-popup'); const form = document.getElementById('beely-page-form');
        form.querySelector('#beely-popup-duplicate').style.display = 'inline-flex';

        const deleteBtn = form.querySelector('#beely-popup-delete');
        const homeBtn = form.querySelector('#beely-popup-sethome');

        const hasChildren = lastPagesCache.some(p => p.parent == id);
        const isFront = (fullData.id === frontPageId);

        // Un bouton qui ne peut qu'échouer ne s'affiche pas : le serveur refuse
        // déjà l'action, mais l'apprendre après-coup est une mauvaise surprise.
        deleteBtn.style.display = (hasChildren || isFront || !peutSupprimer) ? 'none' : 'inline-flex';
        homeBtn.style.display = (isFront || hasChildren || !peutDefinirAccueil) ? 'none' : 'inline-flex';

        // GESTION PARENT SELECT (CACHE SI HOME)
        const parentContainer = document.getElementById('beely-parent-container');
        if (isFront) {
            if (parentContainer) parentContainer.style.display = 'none';
        } else {
            if (parentContainer) parentContainer.style.display = 'block';
        }

        form.querySelector('[name=page_id]').value = fullData.id;
        form.querySelector('[name=post_title]').value = fullData.title;

        // SLUG : '/' et DISABLED SI HOME
        const slugInput = form.querySelector('[name=post_name]');
        if (isFront) {
            slugInput.value = '/';
            slugInput.disabled = true;
        } else {
            slugInput.value = fullData.slug;
            slugInput.disabled = false;
        }

        // STATUS : FORCE PUBLISH SI HOME
        const statusSel = form.querySelector('[name=post_status]');
        statusSel.value = fullData.status;
        if (isFront) {
            statusSel.value = 'publish';
            statusSel.disabled = true;
        } else {
            statusSel.disabled = false;
        }

        form.querySelector('[name=seo_title]').value = fullData.seo_title || '';
        form.querySelector('[name=seo_desc]').value = fullData.seo_desc || '';
        form.querySelector('[name=seo_canonical]').value = fullData.seo_canonical || '';
        majCompteurs();

        form.querySelector('[name=noindex]').checked = fullData.noindex === true;

        const feedback = document.getElementById('beely-slug-feedback');
        const saveBtn = document.getElementById('beely-popup-save');
        feedback.style.display = 'none';
        isSlugValid = true;
        saveBtn.disabled = false;
        saveBtn.style.opacity = '1';

        if (fullData.thumbnail_id && fullData.thumbnail_url) setImage(fullData.thumbnail_id, fullData.thumbnail_url, true);
        else removeImage(true); // SILENT REMOVE

        fillParentSelect(form, fullData.parent, fullData.id); enableSlugSync = false; popup.style.display = 'block';

        // IMPORTANT: RESET FLAG AGAIN AFTER DOM UPDATES
        setTimeout(() => { hasUnsavedChanges = false; }, 50);
    }

    // ----------------------------------------------------------
    // NEW FUNCTION: HIERARCHICAL PARENT SELECT
    // ----------------------------------------------------------
    function fillParentSelect(form, currentParentId, currentPageId) {
        const select = form.querySelector('[name=post_parent]');
        select.innerHTML = '<option value="0">(Racine)</option>';

        // 1. Hierarchy Map
        const hierarchy = {};
        lastPagesCache.forEach(p => {
            if (!hierarchy[p.parent]) hierarchy[p.parent] = [];
            hierarchy[p.parent].push(p);
        });

        // 2. Recursive Builder
        function buildOptions(parentId, depth) {
            if (!hierarchy[parentId]) return;

            hierarchy[parentId].forEach(page => {
                // EXCLUDE SELF & HOME
                if (page.id === currentPageId || page.id === frontPageId) return;

                const option = document.createElement('option');
                option.value = page.id;

                // Visual Indentation
                let prefix = '';
                if (depth > 0) {
                    prefix = '\u00A0\u00A0\u00A0\u00A0'.repeat(depth) + '└─ ';
                }

                // Icons
                const hasChildren = hierarchy[page.id] && hierarchy[page.id].length > 0;
                const icon = hasChildren ? '📁 ' : '📄 ';

                option.textContent = prefix + icon + page.title;

                if (page.id === currentParentId) option.selected = true;
                select.appendChild(option);

                // Recurse
                buildOptions(page.id, depth + 1);
            });
        }

        buildOptions(0, 0);
    }
    // ----------------------------------------------------------

    function closePopup() {
        document.getElementById('beely-page-popup').style.display = 'none';
        highlightEditingItem(null);
    }

    async function setHomepage() {
        if (isProcessing) return;
        const id = document.getElementById('beely-page-form').querySelector('[name=page_id]').value;
        const confirm = await showConfirm('Page d\'accueil', 'Définir cette page comme page d\'accueil ? Elle sera déplacée en haut de la liste.');
        if (!confirm) return;

        isProcessing = true;
        try {
            const resp = await fetch(ajaxUrl, { method: 'POST', body: new URLSearchParams({ action: 'beely_pages_definir_accueil', nonce, page_id: id }) });
            const data = await resp.json();
            if (data.success) {
                closePopup(); await loadPages(true); showToast("Page d'accueil mise à jour !");
            } else {
                showToast(data.data.message || "Erreur", "error");
            }
        } catch (e) { showToast("Erreur serveur", "error"); }
        finally { isProcessing = false; }
    }

    async function duplicatePage() {
        if (isProcessing) return;
        const id = document.getElementById('beely-page-form').querySelector('[name=page_id]').value;
        const confirm = await showConfirm('Dupliquer', 'Voulez-vous dupliquer cette page et tout son contenu ?');
        if (!confirm) return;

        isProcessing = true;
        try {
            const resp = await fetch(ajaxUrl, { method: 'POST', body: new URLSearchParams({ action: 'beely_pages_dupliquer', nonce, page_id: id }) });
            const data = await resp.json();
            if (data.success) {
                closePopup(); await loadPages(true);
                if (data.data.new_id) { const p = lastPagesCache.find(x => x.id == data.data.new_id); if (p) { openEditPopup(p.id, p); enableSlugSync = true; } }
                showToast("Page dupliquée !");
            }
        } catch (e) { showToast("Erreur duplication", "error"); }
        finally { isProcessing = false; }
    }

    async function deletePage() {
        if (isProcessing) return;
        const id = document.getElementById('beely-page-form').querySelector('[name=page_id]').value;
        const confirm = await showConfirm('Supprimer', 'Voulez-vous vraiment mettre cette page à la corbeille ?', true);
        if (!confirm) return;

        isProcessing = true;
        try {
            const resp = await fetch(ajaxUrl, { method: 'POST', body: new URLSearchParams({ action: 'beely_pages_supprimer', nonce, page_id: id }) });
            const data = await resp.json();
            if (data.success) { closePopup(); loadPages(true); showToast("Page supprimée"); }
            else { showToast(data.data.message || "Erreur suppression", "error"); }
        } catch (e) { showToast("Erreur serveur", "error"); }
        finally { isProcessing = false; }
    }

    async function savePage(e, closeAfter = true) {
        if (e) e.preventDefault();
        if (isProcessing) return;

        if (!isSlugValid) {
            showToast("Le slug est invalide ou déjà utilisé", "error");
            return;
        }

        const form = document.getElementById('beely-page-form');
        const fd = new FormData(form);
        fd.append('action', fd.get('page_id') ? 'beely_pages_modifier' : 'beely_pages_creer');
        fd.append('nonce', nonce);

        isProcessing = true;
        try {
            const resp = await fetch(ajaxUrl, { method: 'POST', body: new URLSearchParams(fd) });
            const res = await resp.json();
            if (res.success) {
                if (closeAfter) closePopup();
                loadPages(true);
                showToast("Enregistré");
                hasUnsavedChanges = false; // Reset flag
            } else showToast(res.data.message, "error");
        } catch (e) { showToast("Erreur réseau", "error"); }
        finally { isProcessing = false; }
    }

    async function checkSlugAvailability() {
        const form = document.getElementById('beely-page-form');
        const slug = form.querySelector('[name=post_name]').value;
        const id = form.querySelector('[name=page_id]').value || 0;
        const parentId = form.querySelector('[name=post_parent]').value || 0;
        const feedback = document.getElementById('beely-slug-feedback');
        const saveBtn = document.getElementById('beely-popup-save');

        if (!slug) {
            feedback.style.display = 'none';
            isSlugValid = true;
            saveBtn.disabled = false;
            saveBtn.style.opacity = '1';
            return;
        }

        const resp = await fetch(ajaxUrl, {
            method: 'POST',
            body: new URLSearchParams({
                action: 'beely_pages_verifier_slug',
                nonce,
                slug: slug,
                exclude_id: id,
                parent_id: parentId
            })
        });
        const data = await resp.json();

        if (data.success) {
            feedback.style.display = 'block';
            if (data.data.available) {
                feedback.textContent = "Slug disponible"; feedback.className = "ok"; isSlugValid = true; saveBtn.disabled = false; saveBtn.style.opacity = '1';
            }
            else {
                feedback.textContent = "Slug déjà existant dans ce dossier !"; feedback.className = "error"; isSlugValid = false; saveBtn.disabled = true; saveBtn.style.opacity = '0.5';
            }
        }
    }
    function debounce(fn, t) { let to; return function (...a) { clearTimeout(to); to = setTimeout(() => fn.apply(this, a), t); } }

    /**
     * Longueurs recommandées, affichées à côté de leur champ.
     *
     * Ce ne sont pas des limites : Google recompose ce qu'il veut. Mais un
     * titre de 90 signes est tronqué dans les résultats, et personne ne le
     * découvre avant que la page ne soit indexée.
     */
    const BORNES = { seo_title: [30, 60], seo_desc: [70, 160] };

    function majCompteurs() {
        document.querySelectorAll('.beely-compteur').forEach((sortie) => {
            const champ = document.querySelector(`#beely-page-form [name=${sortie.dataset.pour}]`);
            if (!champ) return;

            const n = champ.value.trim().length;
            const [min, max] = BORNES[sortie.dataset.pour] || [0, Infinity];

            sortie.textContent = n ? `${n}` : '';
            sortie.className = 'beely-compteur' + (n === 0 ? '' : n < min ? ' court' : n > max ? ' long' : ' ok');
            sortie.title = n === 0 ? '' : n < min ? `Court — visez ${min} à ${max} signes.`
                : n > max ? `Long — au-delà de ${max} signes, la fin est tronquée.`
                : `Bonne longueur (${min} à ${max}).`;
        });
    }

    document.addEventListener('input', (e) => {
        if (e.target.closest('#beely-page-form')) majCompteurs();
    });
})();
