<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js" crossorigin="anonymous" nonce="<?= cspNonce() ?>"></script>
<script nonce="<?= cspNonce() ?>">
(function () {
    var ctx = document.getElementById(<?= json_encode($chartId ?? '') ?>);
    if (!ctx) return;

    var valueFormat = <?= json_encode($valueFormat ?? 'number') ?>;
    var formatter = new Intl.NumberFormat('fr-FR', valueFormat === 'currency'
        ? { style: 'currency', currency: 'EUR', maximumFractionDigits: 2 }
        : { maximumFractionDigits: 0 }
    );
    function formatValue(value) {
        return formatter.format(Number(value) || 0);
    }

    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: <?= json_encode($chartLabels ?? [], JSON_UNESCAPED_UNICODE) ?>,
            datasets: [{
                label: <?= json_encode($datasetLabel ?? 'Nb commandes') ?>,
                data: <?= json_encode($chartData ?? []) ?>,
                backgroundColor: 'rgba(212,168,67,0.75)',
                borderColor: 'rgba(212,168,67,1)',
                borderWidth: 1,
                borderRadius: 4
            }]
        },
        options: {
            <?= !empty($horizontal) ? "indexAxis: 'y'," : '' ?>
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false }
                <?php if (!empty($horizontal)): ?>,
                tooltip: {
                    callbacks: {
                        label: function (ctx) {
                            return ' ' + formatValue(ctx.parsed.x);
                        }
                    }
                }
                <?php endif; ?>
            },
            scales: {
                <?= !empty($horizontal) ? 'x' : 'y' ?>: {
                    beginAtZero: true,
                    ticks: <?= !empty($horizontal) ? '{ callback: function (val) { return formatValue(val); } }' : '{ stepSize: 1 }' ?>
                }
            }
        }
    });
})();
</script>
