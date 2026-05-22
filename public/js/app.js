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
