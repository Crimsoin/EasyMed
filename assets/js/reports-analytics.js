/**
 * Reports & Analytics JavaScript Module
 * Handles all chart rendering and tab functionality for the reports page
 */

class ReportsAnalytics {
    constructor() {
        this.charts = {};
        this.init();
    }

    init() {
        this.initTabFunctionality();
        this.renderCharts();
    }

    // Tab Navigation Functionality
    initTabFunctionality() {
        const tabButtons = document.querySelectorAll('.tab-button');
        const tabContents = document.querySelectorAll('.tab-content');

        tabButtons.forEach(button => {
            button.addEventListener('click', (e) => {
                const targetTab = e.currentTarget.getAttribute('data-tab');

                // Remove active class from all buttons and contents
                tabButtons.forEach(btn => btn.classList.remove('active'));
                tabContents.forEach(content => content.classList.remove('active'));

                // Add active class to clicked button and corresponding content
                e.currentTarget.classList.add('active');
                document.getElementById(targetTab + '-tab').classList.add('active');
            });
        });
    }

    // Chart Rendering Methods
    renderCharts() {
        this.renderStatusPieChart();
        this.renderDailyTrendsChart();
        this.renderSpecialtyRevenueChart();
        this.renderDailyRevenueChart();
        this.renderMonthlyRevenueChart();
        this.renderHourlyChart();
        this.renderRevenueAppointmentsChart();
        this.renderDoctorRevenueChart();
        this.renderDoctorWorkloadChart();
        this.renderWeeklyPatternChart();
        this.renderCompletionTrendsChart();
        this.renderAgeDistributionChart();
        this.renderCancellationChart();
        this.renderMultiTrendChart();
        this.renderPaymentStatusPieChart();
        this.renderMonthlyComparisonBarChart();
        this.renderPerformanceStatusPieChart();
        this.renderDoctorPerformanceBarChart();
    }

    // Chart Configuration Helper
    getDefaultChartOptions(responsive = true, maintainAspectRatio = false) {
        return {
            responsive,
            maintainAspectRatio,
            plugins: {
                legend: {
                    display: false
                }
            }
        };
    }

    // Cyan Color Palette
    getCyanColorPalette() {
        return [
            '#00bcd4', '#26c6da', '#4dd0e1', '#80deea', '#b2ebf2',
            '#0097a7', '#00acc1', '#0fb8c7', '#1dbcc8', '#2ac0ca'
        ];
    }

    // Individual Chart Render Methods
    renderStatusPieChart() {
        const ctx = document.getElementById('statusPieChart');
        if (!ctx || !window.appointmentStats) return;

        this.charts.statusPie = new Chart(ctx.getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: ['Completed', 'Confirmed', 'Pending', 'Cancelled', 'No Show'],
                datasets: [{
                    data: [
                        window.appointmentStats.completed,
                        window.appointmentStats.confirmed,
                        window.appointmentStats.pending,
                        window.appointmentStats.cancelled,
                        window.appointmentStats.no_show
                    ],
                    backgroundColor: ['#4caf50', '#2196f3', '#ff9800', '#f44336', '#9e9e9e'],
                    borderWidth: 0
                }]
            },
            options: {
                ...this.getDefaultChartOptions(),
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: (context) => `${context.label}: ${context.parsed}`
                        }
                    }
                }
            }
        });
    }

    renderDailyTrendsChart() {
        const ctx = document.getElementById('dailyTrendsChart');
        if (!ctx || !window.dailyTrends) return;

        this.charts.dailyTrends = new Chart(ctx.getContext('2d'), {
            type: 'line',
            data: {
                labels: window.dailyTrends.labels,
                datasets: [{
                    label: 'Total Appointments',
                    data: window.dailyTrends.total,
                    borderColor: '#00bcd4',
                    backgroundColor: 'rgba(0, 188, 212, 0.1)',
                    tension: 0.4,
                    fill: true
                }, {
                    label: 'Completed',
                    data: window.dailyTrends.completed,
                    borderColor: '#4caf50',
                    backgroundColor: 'rgba(76, 175, 80, 0.1)',
                    tension: 0.4
                }]
            },
            options: {
                ...this.getDefaultChartOptions(),
                scales: {
                    y: { beginAtZero: true }
                },
                plugins: {
                    legend: { display: true },
                    tooltip: {
                        callbacks: {
                            label: (context) => `${context.dataset.label}: ${context.parsed.y}`
                        }
                    }
                }
            }
        });
    }

    renderSpecialtyRevenueChart() {
        const ctx = document.getElementById('specialtyRevenueChart');
        if (!ctx || !window.specialtyRevenue) return;

        this.charts.specialtyRevenue = new Chart(ctx.getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: window.specialtyRevenue.labels,
                datasets: [{
                    data: window.specialtyRevenue.data,
                    backgroundColor: this.getCyanColorPalette(),
                    borderWidth: 0
                }]
            },
            options: {
                ...this.getDefaultChartOptions(),
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: (context) => `${context.label}: $${context.parsed.toLocaleString()}`
                        }
                    }
                }
            }
        });
    }

    renderDailyRevenueChart() {
        const ctx = document.getElementById('dailyRevenueChart');
        if (!ctx || !window.dailyRevenue) return;

        this.charts.dailyRevenue = new Chart(ctx.getContext('2d'), {
            type: 'line',
            data: {
                labels: window.dailyRevenue.labels,
                datasets: [{
                    label: 'Daily Revenue',
                    data: window.dailyRevenue.data,
                    borderColor: '#4caf50',
                    backgroundColor: 'rgba(76, 175, 80, 0.1)',
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                ...this.getDefaultChartOptions(),
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: (value) => '$' + value.toLocaleString()
                        }
                    }
                },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: (context) => `Revenue: $${context.parsed.y.toLocaleString()}`
                        }
                    }
                }
            }
        });
    }

    renderMonthlyRevenueChart() {
        const ctx = document.getElementById('monthlyRevenueChart');
        if (!ctx || !window.monthlyRevenue) return;

        this.charts.monthlyRevenue = new Chart(ctx.getContext('2d'), {
            type: 'bar',
            data: {
                labels: window.monthlyRevenue.labels,
                datasets: [{
                    label: 'Monthly Revenue',
                    data: window.monthlyRevenue.data,
                    backgroundColor: '#00bcd4',
                    borderRadius: 4
                }]
            },
            options: {
                ...this.getDefaultChartOptions(),
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: (value) => '$' + value.toLocaleString()
                        }
                    }
                },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: (context) => `Revenue: $${context.parsed.y.toLocaleString()}`
                        }
                    }
                }
            }
        });
    }

    renderHourlyChart() {
        const ctx = document.getElementById('hourlyChart');
        if (!ctx || !window.hourlyStats) return;

        this.charts.hourly = new Chart(ctx.getContext('2d'), {
            type: 'bar',
            data: {
                labels: window.hourlyStats.labels,
                datasets: [{
                    label: 'Appointments',
                    data: window.hourlyStats.data,
                    backgroundColor: '#2196f3',
                    borderRadius: 4
                }]
            },
            options: {
                ...this.getDefaultChartOptions(),
                scales: {
                    y: { beginAtZero: true }
                },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: (context) => `Appointments: ${context.parsed.y}`
                        }
                    }
                }
            }
        });
    }

    renderRevenueAppointmentsChart() {
        const ctx = document.getElementById('revenueAppointmentsChart');
        if (!ctx || !window.doctorPerformance) return;

        this.charts.revenueAppointments = new Chart(ctx.getContext('2d'), {
            type: 'scatter',
            data: {
                datasets: [{
                    label: 'Revenue vs Appointments',
                    data: window.doctorPerformance.scatter,
                    backgroundColor: 'rgba(0, 188, 212, 0.6)',
                    borderColor: '#00bcd4',
                    borderWidth: 2,
                    pointRadius: 6
                }]
            },
            options: {
                ...this.getDefaultChartOptions(),
                scales: {
                    x: {
                        title: { display: true, text: 'Completed Appointments' }
                    },
                    y: {
                        title: { display: true, text: 'Revenue ($)' },
                        ticks: {
                            callback: (value) => '$' + value.toLocaleString()
                        }
                    }
                },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: (context) => `Appointments: ${context.parsed.x}, Revenue: $${context.parsed.y.toLocaleString()}`
                        }
                    }
                }
            }
        });
    }

    renderDoctorRevenueChart() {
        const ctx = document.getElementById('doctorRevenueChart');
        if (!ctx || !window.doctorPerformance) return;

        this.charts.doctorRevenue = new Chart(ctx.getContext('2d'), {
            type: 'polarArea',
            data: {
                labels: window.doctorPerformance.names.slice(0, 8),
                datasets: [{
                    data: window.doctorPerformance.revenue.slice(0, 8),
                    backgroundColor: [
                        'rgba(255, 99, 132, 0.7)', 'rgba(54, 162, 235, 0.7)',
                        'rgba(255, 205, 86, 0.7)', 'rgba(75, 192, 192, 0.7)',
                        'rgba(153, 102, 255, 0.7)', 'rgba(255, 159, 64, 0.7)',
                        'rgba(199, 199, 199, 0.7)', 'rgba(83, 102, 255, 0.7)'
                    ],
                    borderWidth: 2
                }]
            },
            options: {
                ...this.getDefaultChartOptions(),
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: (context) => `${context.label}: $${context.parsed.toLocaleString()}`
                        }
                    }
                }
            }
        });
    }

    renderDoctorWorkloadChart() {
        const ctx = document.getElementById('doctorWorkloadChart');
        if (!ctx || !window.doctorWorkload) return;

        this.charts.doctorWorkload = new Chart(ctx.getContext('2d'), {
            type: 'bar',
            data: {
                labels: window.doctorWorkload.names.slice(0, 8),
                datasets: [{
                    label: 'Completed',
                    data: window.doctorWorkload.completed.slice(0, 8),
                    backgroundColor: '#4caf50',
                    borderRadius: 4
                }, {
                    label: 'Pending',
                    data: window.doctorWorkload.pending.slice(0, 8),
                    backgroundColor: '#ff9800',
                    borderRadius: 4
                }, {
                    label: 'Confirmed',
                    data: window.doctorWorkload.confirmed.slice(0, 8),
                    backgroundColor: '#2196f3',
                    borderRadius: 4
                }]
            },
            options: {
                ...this.getDefaultChartOptions(),
                indexAxis: 'y',
                scales: {
                    x: { stacked: true },
                    y: { stacked: true }
                },
                plugins: {
                    legend: { display: true },
                    tooltip: {
                        callbacks: {
                            label: (context) => `${context.dataset.label}: ${context.parsed.x}`
                        }
                    }
                }
            }
        });
    }

    renderWeeklyPatternChart() {
        const ctx = document.getElementById('weeklyPatternChart');
        if (!ctx || !window.weeklyStats) return;

        this.charts.weeklyPattern = new Chart(ctx.getContext('2d'), {
            type: 'radar',
            data: {
                labels: ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'],
                datasets: [{
                    label: 'Appointments',
                    data: window.weeklyStats.data,
                    borderColor: '#00bcd4',
                    backgroundColor: 'rgba(0, 188, 212, 0.2)',
                    pointBackgroundColor: '#00bcd4',
                    pointBorderColor: '#fff',
                    pointHoverBackgroundColor: '#fff',
                    pointHoverBorderColor: '#00bcd4'
                }]
            },
            options: {
                ...this.getDefaultChartOptions(),
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    r: { beginAtZero: true }
                }
            }
        });
    }

    renderCompletionTrendsChart() {
        const ctx = document.getElementById('completionTrendsChart');
        if (!ctx || !window.dailyTrends) return;

        this.charts.completionTrends = new Chart(ctx.getContext('2d'), {
            type: 'line',
            data: {
                labels: window.dailyTrends.labels,
                datasets: [{
                    label: 'Total Appointments',
                    data: window.dailyTrends.total,
                    borderColor: '#2196f3',
                    backgroundColor: 'rgba(33, 150, 243, 0.1)',
                    fill: true,
                    tension: 0.4
                }, {
                    label: 'Completed',
                    data: window.dailyTrends.completed,
                    borderColor: '#4caf50',
                    backgroundColor: 'rgba(76, 175, 80, 0.1)',
                    fill: true,
                    tension: 0.4
                }, {
                    label: 'Cancelled',
                    data: window.dailyTrends.cancelled,
                    borderColor: '#f44336',
                    backgroundColor: 'rgba(244, 67, 54, 0.1)',
                    fill: true,
                    tension: 0.4
                }]
            },
            options: {
                ...this.getDefaultChartOptions(),
                interaction: {
                    mode: 'index',
                    intersect: false
                },
                scales: {
                    y: { beginAtZero: true, stacked: false }
                },
                plugins: {
                    legend: { display: true },
                    tooltip: { mode: 'index', intersect: false }
                }
            }
        });
    }

    renderAgeDistributionChart() {
        const ctx = document.getElementById('ageDistributionChart');
        if (!ctx || !window.ageDistribution) return;

        this.charts.ageDistribution = new Chart(ctx.getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: window.ageDistribution.labels,
                datasets: [{
                    data: window.ageDistribution.data,
                    backgroundColor: ['#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0', '#9966FF'],
                    borderWidth: 0
                }]
            },
            options: {
                ...this.getDefaultChartOptions(),
                plugins: {
                    legend: { position: 'bottom' },
                    tooltip: {
                        callbacks: {
                            label: (context) => `${context.label}: ${context.parsed} patients`
                        }
                    }
                }
            }
        });
    }

    renderCancellationChart() {
        const ctx = document.getElementById('cancellationChart');
        if (!ctx || !window.cancellationAnalysis) return;

        this.charts.cancellation = new Chart(ctx.getContext('2d'), {
            type: 'bar',
            data: {
                labels: window.cancellationAnalysis.labels,
                datasets: [{
                    label: 'Count',
                    data: window.cancellationAnalysis.data,
                    backgroundColor: ['#f44336', '#9e9e9e'],
                    borderRadius: 4
                }]
            },
            options: {
                ...this.getDefaultChartOptions(),
                scales: {
                    y: { beginAtZero: true }
                }
            }
        });
    }

    renderMultiTrendChart() {
        const ctx = document.getElementById('multiTrendChart');
        if (!ctx || !window.dailyRevenue) return;

        this.charts.multiTrend = new Chart(ctx.getContext('2d'), {
            type: 'line',
            data: {
                labels: window.dailyRevenue.labels,
                datasets: [{
                    type: 'line',
                    label: 'Revenue ($)',
                    data: window.dailyRevenue.revenue,
                    borderColor: '#4caf50',
                    backgroundColor: 'rgba(76, 175, 80, 0.1)',
                    yAxisID: 'y',
                    tension: 0.4
                }, {
                    type: 'bar',
                    label: 'Appointments',
                    data: window.dailyRevenue.appointments,
                    backgroundColor: 'rgba(0, 188, 212, 0.7)',
                    yAxisID: 'y1'
                }]
            },
            options: {
                ...this.getDefaultChartOptions(),
                interaction: {
                    mode: 'index',
                    intersect: false
                },
                scales: {
                    y: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                        title: { display: true, text: 'Revenue ($)' },
                        ticks: {
                            callback: (value) => '$' + value.toLocaleString()
                        }
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        title: { display: true, text: 'Appointments' },
                        grid: { drawOnChartArea: false }
                    }
                },
                plugins: {
                    legend: { display: true },
                    tooltip: {
                        callbacks: {
                            label: (context) => {
                                if (context.dataset.label === 'Revenue ($)') {
                                    return `Revenue: $${context.parsed.y.toLocaleString()}`;
                                }
                                return `${context.dataset.label}: ${context.parsed.y}`;
                            }
                        }
                    }
                }
            }
        });
    }

    renderPaymentStatusPieChart() {
        const ctx = document.getElementById('paymentStatusPieChart');
        if (!ctx || !window.appointmentStats) return;

        this.charts.paymentStatus = new Chart(ctx.getContext('2d'), {
            type: 'pie',
            data: {
                labels: ['Completed Payments', 'Pending Payments', 'Cancelled'],
                datasets: [{
                    data: [
                        window.appointmentStats.completed,
                        window.appointmentStats.confirmed + window.appointmentStats.pending,
                        window.appointmentStats.cancelled + window.appointmentStats.no_show
                    ],
                    backgroundColor: ['#4caf50', '#ff9800', '#f44336'],
                    borderWidth: 2,
                    borderColor: '#fff'
                }]
            },
            options: {
                ...this.getDefaultChartOptions(),
                plugins: {
                    legend: { position: 'bottom' },
                    tooltip: {
                        callbacks: {
                            label: (context) => {
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = ((context.parsed / total) * 100).toFixed(1);
                                return `${context.label}: ${context.parsed} (${percentage}%)`;
                            }
                        }
                    }
                }
            }
        });
    }

    renderMonthlyComparisonBarChart() {
        const ctx = document.getElementById('monthlyComparisonBarChart');
        if (!ctx || !window.monthlyRevenue) return;

        this.charts.monthlyComparison = new Chart(ctx.getContext('2d'), {
            type: 'bar',
            data: {
                labels: window.monthlyRevenue.labels.slice(-6),
                datasets: [{
                    label: 'Revenue',
                    data: window.monthlyRevenue.data.slice(-6),
                    backgroundColor: this.getCyanColorPalette().slice(0, 6),
                    borderColor: ['#00acc1', '#43a047', '#fb8c00', '#1976d2', '#7b1fa2', '#d32f2f'],
                    borderWidth: 2,
                    borderRadius: 6
                }]
            },
            options: {
                ...this.getDefaultChartOptions(),
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: (value) => '$' + value.toLocaleString()
                        }
                    }
                },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: (context) => `Revenue: $${context.parsed.y.toLocaleString()}`
                        }
                    }
                }
            }
        });
    }

    renderPerformanceStatusPieChart() {
        const ctx = document.getElementById('performanceStatusPieChart');
        if (!ctx || !window.appointmentStats) return;

        this.charts.performanceStatus = new Chart(ctx.getContext('2d'), {
            type: 'pie',
            data: {
                labels: ['Completed', 'Confirmed', 'Pending', 'Cancelled', 'No Show'],
                datasets: [{
                    data: [
                        window.appointmentStats.completed,
                        window.appointmentStats.confirmed,
                        window.appointmentStats.pending,
                        window.appointmentStats.cancelled,
                        window.appointmentStats.no_show
                    ],
                    backgroundColor: ['#4caf50', '#2196f3', '#ff9800', '#f44336', '#9e9e9e'],
                    borderWidth: 3,
                    borderColor: '#fff',
                    hoverBorderWidth: 4
                }]
            },
            options: {
                ...this.getDefaultChartOptions(),
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            padding: 20,
                            usePointStyle: true
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: (context) => {
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = ((context.parsed / total) * 100).toFixed(1);
                                return `${context.label}: ${context.parsed} (${percentage}%)`;
                            }
                        }
                    }
                }
            }
        });
    }

    renderDoctorPerformanceBarChart() {
        const ctx = document.getElementById('doctorPerformanceBarChart');
        if (!ctx || !window.doctorPerformance) return;

        this.charts.doctorPerformanceBar = new Chart(ctx.getContext('2d'), {
            type: 'bar',
            data: {
                labels: window.doctorPerformance.names.slice(0, 6),
                datasets: [{
                    label: 'Completed',
                    data: window.doctorPerformance.completed.slice(0, 6),
                    backgroundColor: '#4caf50',
                    borderColor: '#388e3c',
                    borderWidth: 1
                }, {
                    label: 'Cancelled',
                    data: window.doctorPerformance.cancelled.slice(0, 6),
                    backgroundColor: '#f44336',
                    borderColor: '#d32f2f',
                    borderWidth: 1
                }, {
                    label: 'No Shows',
                    data: window.doctorPerformance.no_shows.slice(0, 6),
                    backgroundColor: '#9e9e9e',
                    borderColor: '#757575',
                    borderWidth: 1
                }]
            },
            options: {
                ...this.getDefaultChartOptions(),
                scales: {
                    x: { stacked: false },
                    y: { stacked: false, beginAtZero: true }
                },
                plugins: {
                    legend: { position: 'top' },
                    tooltip: {
                        callbacks: {
                            label: (context) => `${context.dataset.label}: ${context.parsed.y}`
                        }
                    }
                }
            }
        });
    }

    // Utility method to destroy all charts (useful for cleanup)
    destroyAllCharts() {
        Object.values(this.charts).forEach(chart => {
            if (chart && typeof chart.destroy === 'function') {
                chart.destroy();
            }
        });
        this.charts = {};
    }
}

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    if (typeof Chart !== 'undefined') {
        window.reportsAnalytics = new ReportsAnalytics();
    } else {
        console.error('Chart.js is not loaded. Please include Chart.js before this script.');
    }
});
