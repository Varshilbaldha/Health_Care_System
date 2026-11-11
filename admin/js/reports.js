// Database-driven reports charts and reliable server-side downloads.
const d = window.reportChartData || {};

const revenueChart = document.getElementById('revenueChart');
if (revenueChart) {
    new Chart(revenueChart, {
        type: 'line',
        data: {
            labels: d.revenueLabels || [],
            datasets: [{
                label: 'Revenue',
                data: d.revenueData || [],
                borderColor: '#28bf37',
                backgroundColor: 'rgba(160,239,180,.17)',
                borderWidth: 2,
                fill: true,
                tension: .4,
                pointRadius: 3
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                x: { grid: { color: '#f0f1f6ab' } },
                y: { beginAtZero: true, grid: { color: '#f0f1f6ab' } }
            }
        }
    });
}

const departmentChart = document.getElementById('departmentChart');
if (departmentChart) {
    new Chart(departmentChart, {
        type: 'doughnut',
        data: {
            labels: d.departmentLabels || [],
            datasets: [{
                data: d.departmentData || [],
                backgroundColor: ['#f56fee','#78c8e5','#63f325','#6ce1ba','#635bff','#ffb84d','#ef6b6b','#7c8cff'],
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '70%',
            plugins: { legend: { position: 'right', labels: { boxWidth: 12, font: { size: 12 } } } }
        }
    });
}

const appointmentChart = document.getElementById('appointmentChart');
if (appointmentChart) {
    new Chart(appointmentChart, {
        type: 'bar',
        data: {
            labels: d.appointmentLabels || [],
            datasets: [{
                data: d.appointmentData || [],
                backgroundColor: '#635BFF',
                borderRadius: 8,
                borderSkipped: false
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                x: { grid: { display: false } },
                y: { beginAtZero: true, grid: { color: '#eef2f7' } }
            }
        }
    });
}

/*
 * Use a hidden iframe instead of fetch/blob so PHP can send a real
 * Content-Disposition attachment. This also works when the server
 * returns a binary PDF/XLS file.
 */
function startDownload(url) {
    const iframe = document.createElement('iframe');
    iframe.style.display = 'none';
    iframe.setAttribute('aria-hidden', 'true');
    iframe.src = url;
    document.body.appendChild(iframe);
    window.setTimeout(() => iframe.remove(), 60000);
}

document.getElementById('exportPdf')?.addEventListener('click', () => {
    startDownload('reports_export.php?format=pdf&report=All%20Reports');
});

document.getElementById('exportExcel')?.addEventListener('click', () => {
    startDownload('reports_export.php?format=excel&report=All%20Reports');
});

document.querySelectorAll('.report-download').forEach((button) => {
    button.addEventListener('click', () => {
        const report = button.dataset.report || 'All Reports';
        startDownload('reports_export.php?format=excel&report=' + encodeURIComponent(report));
    });
});
