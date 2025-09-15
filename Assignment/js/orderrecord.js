/**
 * Order History JavaScript functionality
 * Handles pie chart creation for order status distribution
 */

/**
 * Initialize the order status distribution pie chart
 * @param {Array} chartLabels - Array of status labels
 * @param {Array} chartData - Array of order counts for each status
 * @param {Array} chartColors - Array of colors for each status
 */
function initializeStatusChart(chartLabels, chartData, chartColors) {
    // Pie Chart for Order Status Distribution
    document.addEventListener('DOMContentLoaded', function() {
        const ctx = document.getElementById('statusChart').getContext('2d');
        
        const chartDataConfig = {
            labels: chartLabels,
            datasets: [{
                data: chartData,
                backgroundColor: chartColors,
                borderColor: '#fff',
                borderWidth: 2,
                hoverBorderWidth: 3
            }]
        };

        const statusChart = new Chart(ctx, {
            type: 'doughnut',
            data: chartDataConfig,
            options: {
                responsive: true,
                maintainAspectRatio: true,
                cutout: '60%', // This creates the ring effect (60% hollow center)
                plugins: {
                    legend: {
                        display: false // We use custom legend
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const label = context.label || '';
                                const value = context.parsed;
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = ((value / total) * 100).toFixed(1);
                                return `${label}: ${value} orders (${percentage}%)`;
                            }
                        }
                    }
                },
                animation: {
                    animateRotate: true,
                    duration: 1000
                }
            }
        });
    });
}
