// public/js/app.js

/* Auto-dismiss des alertes Bootstrap après 4 secondes */
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.alert.alert-dismissible').forEach(function (alert) {
        setTimeout(function () {
            var bsAlert = bootstrap.Alert.getOrCreateInstance(alert);
            bsAlert.close();
        }, 4000);
    });
});

/* Confirmation avant soumission des formulaires sensibles */
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('form.form-confirm').forEach(function (form) {
        form.addEventListener('submit', function (e) {
            if (!confirm('Êtes-vous sûr de vouloir effectuer cette action ?')) {
                e.preventDefault();
            }
        });
    });
});
