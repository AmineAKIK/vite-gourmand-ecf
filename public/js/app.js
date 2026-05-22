// public/js/app.js

document.addEventListener('DOMContentLoaded', function () {
    /* Auto-dismiss des alertes Bootstrap après 4 secondes */
    document.querySelectorAll('.alert.alert-dismissible').forEach(function (alert) {
        setTimeout(function () {
            var bsAlert = bootstrap.Alert.getOrCreateInstance(alert);
            bsAlert.close();
        }, 4000);
    });

    /* Confirmation avant soumission des formulaires sensibles */
    document.querySelectorAll('form.form-confirm').forEach(function (form) {
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
});
