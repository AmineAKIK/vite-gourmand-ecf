// public/js/app.js

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

    /* Confirmation avant soumission — via data-confirm sur <form> ou <button>/<a> */
    document.querySelectorAll('[data-confirm]').forEach(function (el) {
        var event = (el.tagName === 'FORM') ? 'submit' : 'click';
        el.addEventListener(event, function (e) {
            if (!confirm(el.dataset.confirm || 'Êtes-vous sûr ?')) {
                e.preventDefault();
            }
        });
    });
    // Rétro-compatibilité : class form-confirm sans message personnalisé
    document.querySelectorAll('form.form-confirm:not([data-confirm])').forEach(function (form) {
        form.addEventListener('submit', function (e) {
            if (!confirm('Êtes-vous sûr de vouloir effectuer cette action ?')) {
                e.preventDefault();
            }
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
