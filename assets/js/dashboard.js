const canvas = document.getElementById('sales-chart');
if (canvas && window.Chart) {
    new Chart(canvas, {
        type: 'bar',
        data: {
            labels: JSON.parse(canvas.dataset.labels),
            datasets: [
                {
                    label: 'Pendapatan selesai (Rp)',
                    data: JSON.parse(canvas.dataset.values),
                    backgroundColor: '#a55b30',
                    borderRadius: 5,
                    maxBarThickness: 55
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: (context) =>
                            new Intl.NumberFormat('id-ID', {
                                style: 'currency',
                                currency: 'IDR',
                                maximumFractionDigits: 0
                            }).format(context.raw)
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: (value) => 'Rp' + new Intl.NumberFormat('id-ID').format(value)
                    },
                    grid: { color: '#eee5d9' }
                },
                x: { grid: { display: false } }
            }
        }
    });
}
