// public/js/app.js

(function () {
    function isAbortError(error) {
        return error && (error.name === 'AbortError' || error.code === 20);
    }

    window.vgIsAbortError = isAbortError;

    window.vgFetchJson = async function (url, options) {
        options = options || {};
        var headers = new Headers(options.headers || {});
        headers.set('X-Requested-With', 'XMLHttpRequest');
        headers.set('Accept', 'application/json');

        var response = await fetch(url, Object.assign({}, options, { headers: headers }));
        var data = null;

        try {
            data = await response.json();
        } catch (error) {
            data = null;
        }

        if (!response.ok || (data && data.ok === false)) {
            var message = (data && (data.message || data.error)) || 'Erreur de communication.';
            var fetchError = new Error(message);
            fetchError.status = response.status;
            fetchError.data = data;
            throw fetchError;
        }

        return data;
    };

    window.vgDebounce = function (fn, delay) {
        var timer = null;
        return function () {
            var args = arguments;
            var context = this;
            clearTimeout(timer);
            timer = setTimeout(function () {
                fn.apply(context, args);
            }, delay);
        };
    };
}());

document.addEventListener('DOMContentLoaded', function () {
    /* Réouverture automatique d'un modal après erreur de validation côté serveur
       Le serveur redirige avec ?open_modal=xxx&modal_error=message
       On retrouve le modal par l'id "modalId" = "modalCreerMenu", "modalModifierMenu5", etc.
       Convention : open_modal=creer_menu  → modal id #modalCreerMenu
                    open_modal=modifier_menu_3 → #modalModifierMenu3
                    open_modal=creer_plat  → #modalCreerPlat
                    open_modal=modifier_plat_7 → #modalModifPlat7
                    open_modal=modif_2 → #modifModal2
    */
    var params = new URLSearchParams(window.location.search);
    var openModal = params.get('open_modal');
    var modalError = params.get('modal_error');
    if (openModal) {
        var modalId = openModal
            .replace(/^creer_menu$/, 'modalCreerMenu')
            .replace(/^creer_plat$/, 'modalCreerPlat')
            .replace(/^modifier_menu_(\d+)$/, 'modalModifierMenu$1')
            .replace(/^modifier_plat_(\d+)$/, 'modalModifPlat$1')
            .replace(/^modif_(\d+)$/, 'modifModal$1')
            .replace(/^avis_(\d+)$/, 'avisModal$1');
        var el = document.getElementById(modalId);
        if (el) {
            if (modalError) {
                var errBox = document.getElementById(openModal + '-error');
                if (errBox) {
                    errBox.textContent = decodeURIComponent(modalError);
                    errBox.classList.remove('d-none');
                }
            }
            bootstrap.Modal.getOrCreateInstance(el).show();
            // Nettoyer l'URL sans recharger
            var cleanUrl = window.location.pathname;
            window.history.replaceState({}, '', cleanUrl);
        }
    }

    /* Auto-dismiss des alertes Bootstrap après 4 secondes */
    document.querySelectorAll('.alert.alert-dismissible').forEach(function (alert) {
        setTimeout(function () {
            var bsAlert = bootstrap.Alert.getOrCreateInstance(alert);
            bsAlert.close();
        }, 4000);
    });

    /* Confirmation maison responsive — remplace les dialogues natifs du navigateur. */
    var confirmedForms = new WeakSet();

    function ensureConfirmModal() {
        var existing = document.getElementById('vgConfirmModal');
        if (existing) return existing;

        var wrapper = document.createElement('div');
        wrapper.innerHTML = [
            '<div class="modal fade vg-confirm-modal" id="vgConfirmModal" tabindex="-1" aria-labelledby="vgConfirmTitle" aria-hidden="true">',
            '  <div class="modal-dialog modal-dialog-centered vg-confirm-dialog">',
            '    <div class="modal-content vg-confirm-content">',
            '      <div class="vg-confirm-icon" data-confirm-icon><i class="bi bi-exclamation-circle" aria-hidden="true"></i></div>',
            '      <div class="vg-confirm-body">',
            '        <h2 class="vg-confirm-title" id="vgConfirmTitle">Confirmer l’action</h2>',
            '        <p class="vg-confirm-message" data-confirm-message>Êtes-vous sûr de vouloir effectuer cette action ?</p>',
            '      </div>',
            '      <div class="vg-confirm-actions">',
            '        <button type="button" class="btn btn-vg-outline" data-bs-dismiss="modal" data-confirm-cancel>Annuler</button>',
            '        <button type="button" class="btn btn-vg" data-confirm-submit>Confirmer</button>',
            '      </div>',
            '    </div>',
            '  </div>',
            '</div>'
        ].join('');
        document.body.appendChild(wrapper.firstElementChild);
        return document.getElementById('vgConfirmModal');
    }

    function inferConfirmVariant(source) {
        var action = source && source.getAttribute && (source.getAttribute('action') || source.getAttribute('formaction') || '');
        var className = source && source.className ? String(source.className) : '';
        var text = source && source.textContent ? source.textContent.toLowerCase() : '';
        var dangerWords = /(supprimer|annuler|refuser|désactiver|desactiver|vider|définitive|definitive)/i;
        if (source && source.dataset && source.dataset.confirmVariant) return source.dataset.confirmVariant;
        if (className.indexOf('danger') !== -1 || dangerWords.test(action) || dangerWords.test(text)) return 'danger';
        return 'warning';
    }

    function openConfirmDialog(options) {
        options = options || {};
        var modalEl = ensureConfirmModal();
        var titleEl = modalEl.querySelector('#vgConfirmTitle');
        var messageEl = modalEl.querySelector('[data-confirm-message]');
        var iconEl = modalEl.querySelector('[data-confirm-icon] i');
        var submitBtn = modalEl.querySelector('[data-confirm-submit]');
        var variant = options.variant || 'warning';

        modalEl.classList.toggle('vg-confirm-modal--danger', variant === 'danger');
        modalEl.classList.toggle('vg-confirm-modal--warning', variant !== 'danger');
        titleEl.textContent = options.title || (variant === 'danger' ? 'Confirmer cette action' : 'Confirmation');
        messageEl.textContent = options.message || 'Êtes-vous sûr de vouloir effectuer cette action ?';
        submitBtn.textContent = options.confirmLabel || (variant === 'danger' ? 'Confirmer' : 'Continuer');
        iconEl.className = variant === 'danger' ? 'bi bi-exclamation-triangle' : 'bi bi-exclamation-circle';

        return new Promise(function (resolve) {
            var modal = bootstrap.Modal.getOrCreateInstance(modalEl);

            function cleanup(result) {
                submitBtn.removeEventListener('click', onConfirm);
                modalEl.removeEventListener('hidden.bs.modal', onHidden);
                resolve(result);
            }
            function onConfirm() {
                modal.hide();
                cleanup(true);
            }
            function onHidden() {
                cleanup(false);
            }

            submitBtn.addEventListener('click', onConfirm, { once: true });
            modalEl.addEventListener('hidden.bs.modal', onHidden, { once: true });
            modal.show();
        });
    }

    window.vgConfirm = openConfirmDialog;

    function confirmOptionsFrom(source, fallbackMessage) {
        var dataset = source && source.dataset ? source.dataset : {};
        return {
            title: dataset.confirmTitle || '',
            message: dataset.confirm || fallbackMessage || 'Êtes-vous sûr de vouloir effectuer cette action ?',
            confirmLabel: dataset.confirmAction || '',
            variant: inferConfirmVariant(source)
        };
    }

    function submitConfirmedForm(form, submitter) {
        confirmedForms.add(form);
        if (submitter && typeof form.requestSubmit === 'function') {
            form.requestSubmit(submitter);
            return;
        }
        if (typeof form.requestSubmit === 'function') {
            form.requestSubmit();
            return;
        }
        form.submit();
    }

    document.querySelectorAll('[data-confirm]').forEach(function (el) {
        if (el.tagName === 'FORM') {
            el.addEventListener('submit', function (e) {
                if (confirmedForms.has(el)) {
                    confirmedForms.delete(el);
                    return;
                }
                e.preventDefault();
                openConfirmDialog(confirmOptionsFrom(el)).then(function (ok) {
                    if (ok) submitConfirmedForm(el, e.submitter);
                });
            });
            return;
        }

        el.addEventListener('click', function (e) {
            e.preventDefault();
            openConfirmDialog(confirmOptionsFrom(el)).then(function (ok) {
                if (!ok) return;
                if (el.form) {
                    submitConfirmedForm(el.form, el);
                    return;
                }
                if (el.tagName === 'A' && el.href) {
                    window.location.href = el.href;
                }
            });
        });
    });

    // Rétro-compatibilité : class form-confirm sans message personnalisé.
    document.querySelectorAll('form.form-confirm:not([data-confirm])').forEach(function (form) {
        form.addEventListener('submit', function (e) {
            if (confirmedForms.has(form)) {
                confirmedForms.delete(form);
                return;
            }
            e.preventDefault();
            openConfirmDialog(confirmOptionsFrom(form, 'Êtes-vous sûr de vouloir effectuer cette action ?')).then(function (ok) {
                if (ok) submitConfirmedForm(form, e.submitter);
            });
        });
    });

    /* Aperçu miniatures photos avant upload */
    document.querySelectorAll('.image-picker').forEach(function (input) {
        input.addEventListener('change', function () {
            var container = input.nextElementSibling && input.nextElementSibling.nextElementSibling;
            if (!container || !container.classList.contains('image-preview-container')) return;
            container.innerHTML = '';
            Array.from(input.files).forEach(function (file) {
                if (!file.type.startsWith('image/')) return;
                var reader = new FileReader();
                reader.onload = function (e) {
                    var wrap = document.createElement('div');
                    wrap.style.cssText = 'position:relative;display:inline-block;';
                    var img = document.createElement('img');
                    img.src = e.target.result;
                    img.style.cssText = 'width:70px;height:70px;object-fit:cover;border-radius:8px;border:1px solid rgba(139,26,43,0.2);';
                    img.alt = file.name;
                    wrap.appendChild(img);
                    container.appendChild(wrap);
                };
                reader.readAsDataURL(file);
            });
        });
    });

    /* Afficher/masquer les champs mot de passe */
    document.querySelectorAll('[data-password-toggle]').forEach(function (button) {
        button.addEventListener('click', function () {
            var input = document.getElementById(button.dataset.passwordToggle);
            var icon = button.querySelector('i');
            if (!input || !icon) return;

            var reveal = input.type === 'password';
            input.type = reveal ? 'text' : 'password';
            icon.className = reveal ? 'bi bi-eye-slash' : 'bi bi-eye';
        });
    });

    document.querySelectorAll('[data-print-document]').forEach(function (button) {
        button.addEventListener('click', function () {
            window.print();
        });
    });
});
