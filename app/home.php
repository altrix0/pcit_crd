<?php
header('Content-Type: text/html');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Unit Distribution Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        /* Essential color variables for charts */
        :root {
            --filled-color: #0d6efd; /* Bootstrap primary */
            --vacant-color: #dc3545; /* Bootstrap danger */
            --filled-bg: rgba(13, 110, 253, 0.8);
            --vacant-bg: rgba(220, 53, 69, 0.8);
            --chart-blue-1: rgba(13, 110, 253, 0.8);
            --chart-blue-2: rgba(13, 110, 253, 0.6);
            --chart-blue-3: rgba(13, 110, 253, 0.4);
        }
        
        /* Fixed height for chart container */
        .chart-container {
            position: relative;
            height: 300px;
        }
        
        /* Status indicator dots */
        .status-indicator {
            width: 10px;
            height: 10px;
            display: inline-block;
            border-radius: 50%;
            margin-right: 5px;
        }
        
        .filled-indicator {
            background-color: var(--filled-color);
        }
        
        .vacant-indicator {
            background-color: var(--vacant-color);
        }
        
        /* Refresh button rotation */
        .refresh-button {
            cursor: pointer;
            transition: transform 0.3s ease;
        }
        
        .refresh-button:hover {
            transform: rotate(90deg);
        }
        
        /* Loading animation */
        .rotate {
            animation: rotate 1s linear infinite;
        }
        
        @keyframes rotate {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
        
        /* Mock data indicator */
        .mock-data-badge {
            position: absolute;
            top: 10px;
            right: 10px;
            background-color: rgba(255, 193, 7, 0.9);
            color: #212529;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: bold;
            z-index: 10;
        }
    </style>
</head>
<body>
    <div class="container my-4">
        <!-- Header Section -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="mb-0">Unit Distribution Dashboard</h2>
                <div id="lastUpdated" class="small text-muted mt-1"></div>
            </div>
            <div>
                <i id="refreshButton" class="bi bi-arrow-clockwise fs-4 refresh-button" title="Refresh data"></i>
            </div>
        </div>
        
        <!-- Alert Area -->
        <div id="alertArea"></div>
        
        <!-- Main Chart Card -->
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Personnel Distribution</h5>
                <div id="chartLegend" class="d-flex align-items-center">
                    <span class="me-3"><i class="status-indicator filled-indicator"></i> Filled</span>
                    <span><i class="status-indicator vacant-indicator"></i> Vacant</span>
                </div>
            </div>
            <div class="card-body">
                <div class="row">
                    <!-- Chart Column -->
                    <div class="col-lg-5">
                        <div class="chart-container">
                            <canvas id="mainChart"></canvas>
                        </div>
                        <div id="totalStats" class="row text-center mt-3">
                            <!-- Filled by JS -->
                        </div>
                    </div>
                    
                    <!-- Table Column -->
                    <div class="col-lg-7">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Position</th>
                                        <th class="text-center">Filled</th>
                                        <th class="text-center">Vacant</th>
                                        <th style="width: 25%">Status</th>
                                    </tr>
                                </thead>
                                <tbody id="positionsTableBody">
                                    <!-- Filled by JS -->
                                </tbody>
                                <tfoot id="positionsTableFoot">
                                    <!-- Filled by JS -->
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Equipment Distribution Card -->
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Equipment Distribution</h5>
                <div>
                    <select id="equipmentViewType" class="form-select form-select-sm" style="width: auto; display: inline-block;">
                        <option value="make">By Make</option>
                        <option value="model">By Model</option>
                    </select>
                </div>
            </div>
            <div class="card-body">
                <div id="equipmentChartContainer" class="chart-container">
                    <canvas id="equipmentChart"></canvas>
                </div>
                
                <!-- Equipment loading indicator -->
                <div id="equipmentLoadingIndicator" class="text-center py-5" style="display: none;">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading equipment data...</span>
                    </div>
                </div>
                
                <!-- No equipment data message -->
                <div id="noEquipmentData" class="text-center py-5" style="display: none;">
                    <p class="text-muted mb-0">No equipment data available for this unit.</p>
                </div>
            </div>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Elements
        const refreshButton = document.getElementById('refreshButton');
        const lastUpdatedEl = document.getElementById('lastUpdated');
        const alertArea = document.getElementById('alertArea');
        const positionsTableBody = document.getElementById('positionsTableBody');
        const positionsTableFoot = document.getElementById('positionsTableFoot');
        const totalStatsEl = document.getElementById('totalStats');
        const equipmentViewType = document.getElementById('equipmentViewType');
        const equipmentChartContainer = document.getElementById('equipmentChartContainer');
        const equipmentLoadingIndicator = document.getElementById('equipmentLoadingIndicator');
        const noEquipmentData = document.getElementById('noEquipmentData');
        
        // Chart instances
        let mainChart = null;
        let equipmentChart = null;
        let isLoading = false;
        
        // Load personnel data with optional force refresh
        function loadPersonnelData(forceRefresh = false) {
            if (isLoading) return;
            isLoading = true;
            
            // Visual feedback
            refreshButton.classList.add('rotate');
            
            // Clear any existing alerts
            alertArea.innerHTML = '';
            
            // Endpoint URL with cache-busting if force refresh
            const url = `personnel_chart.php${forceRefresh ? '?t=' + Date.now() : ''}`;
            fetch(url)
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.json();
                })
                .then(data => {
                    displayData(data);
                    isLoading = false;
                    refreshButton.classList.remove('rotate');
                    
                    // After personnel data loads, get equipment data
                    loadEquipmentData(forceRefresh);
                })
                .catch(error => {
                    showError('Failed to load personnel data: ' + error.message);
                    isLoading = false;
                    refreshButton.classList.remove('rotate');
                });
        }
        
        // Load equipment data
        function loadEquipmentData(forceRefresh = false) {
            // Show loading state
            equipmentChartContainer.style.display = 'none';
            equipmentLoadingIndicator.style.display = 'block';
            noEquipmentData.style.display = 'none';
            
            // Remove any existing mock data indicator
            const existingBadge = document.querySelector('.mock-data-badge');
            if (existingBadge) existingBadge.remove();
            // Endpoint URL with cache-busting and view type
            const viewType = equipmentViewType.value;
            const url = `equipment_chart.php?view=${viewType}${forceRefresh ? '&t=' + Date.now() : ''}`;
 
            fetch(url)
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.json();
                })
                .then(data => {
                    // Show appropriate UI based on data
                    equipmentLoadingIndicator.style.display = 'none';
                    
                    if (data && data.length > 0) {
                        equipmentChartContainer.style.display = 'block';
                        createEquipmentChart(data, viewType);
                    } else {
                        noEquipmentData.style.display = 'block';
                    }
                })
                .catch(error => {
                    equipmentLoadingIndicator.style.display = 'none';
                    
                    // Generate mock data instead of showing error
                    const mockData = generateMockEquipmentData(viewType);
                    equipmentChartContainer.style.display = 'block';
                    
                    // Create mock data indicator badge
                    const mockBadge = document.createElement('div');
                    mockBadge.className = 'mock-data-badge';
                    mockBadge.textContent = 'SAMPLE DATA';
                    equipmentChartContainer.appendChild(mockBadge);
                    
                    // Use mock data for chart
                    createEquipmentChart(mockData, viewType);
                    
                    // Show a warning instead of error
                    showError('Using sample equipment data. ' + error.message);
                });       
             }
        
        // Generate mock equipment data based on view type
        function generateMockEquipmentData(viewType) {
            if (viewType === 'make') {
                // Mock data for manufacturers
                return [
                    { make: 'Toyota', model: '', count: 32 },
                    { make: 'Honda', model: '', count: 18 },
                    { make: 'Ford', model: '', count: 14 },
                    { make: 'Chevrolet', model: '', count: 10 },
                    { make: 'Nissan', model: '', count: 8 }
                ];
            } else {
                // Mock data for models
                return [
                    { make: 'Toyota', model: 'Corolla', count: 15 },
                    { make: 'Toyota', model: 'Camry', count: 12 },
                    { make: 'Honda', model: 'Civic', count: 10 },
                    { make: 'Honda', model: 'Accord', count: 8 },
                    { make: 'Ford', model: 'F-150', count: 7 },
                    { make: 'Ford', model: 'Explorer', count: 6 },
                    { make: 'Chevrolet', model: 'Silverado', count: 5 },
                    { make: 'Nissan', model: 'Altima', count: 4 }
                ];
            }
        }
        
        // Show error message
        function showError(message) {
            alertArea.innerHTML = `
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    ${message}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            `;
        }
        
        // Process and display personnel data
        function displayData(data) {
            // Update timestamp
            if (data.timestamp) {
                lastUpdatedEl.textContent = `Last updated: ${data.timestamp}`;
            }
            
            // Process positions data
            const positions = data.positions || [];
            const summary = data.summary || {
                total_sanctioned: 0,
                total_filled: 0,
                total_vacant: 0,
                staffing_percentage: 0
            };
            
            // Update main chart
            createOrUpdateMainChart([summary.total_filled, summary.total_vacant]);
            
            // Update summary stats
            updateSummaryStats(summary);
            
            // Update positions table
            updatePositionsTable(positions, summary);
        }
        
        // Create or update the main chart
        function createOrUpdateMainChart(data) {
            const ctx = document.getElementById('mainChart').getContext('2d');
            
            // Get computed CSS variables
            const computedStyle = getComputedStyle(document.documentElement);
            const filledBgColor = computedStyle.getPropertyValue('--filled-bg').trim() || 'rgba(13, 110, 253, 0.8)';
            const vacantBgColor = computedStyle.getPropertyValue('--vacant-bg').trim() || 'rgba(220, 53, 69, 0.8)';
            
            // Destroy existing chart if it exists
            if (mainChart) {
                mainChart.destroy();
            }
            
            // Create new chart
            mainChart = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: ['Filled', 'Vacant'],
                    datasets: [{
                        data: data,
                        backgroundColor: [
                            filledBgColor,
                            vacantBgColor
                        ],
                        borderColor: [
                            '#fff',
                            '#fff'
                        ],
                        borderWidth: 2,
                        hoverOffset: 10
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '65%',
                    animation: {
                        duration: 1000,
                        easing: 'easeOutQuart'
                    },
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const label = context.label;
                                    const value = context.raw;
                                    const total = context.chart.data.datasets[0].data.reduce((a, b) => a + b, 0);
                                    const percent = Math.round((value / total) * 100);
                                    return `${label}: ${value} (${percent}%)`;
                                }
                            }
                        }
                    }
                }
            });
        }
        
        // Create equipment bar chart with enhanced features
        function createEquipmentChart(data, viewType) {
            const ctx = document.getElementById('equipmentChart').getContext('2d');
            
            // Destroy existing chart if it exists
            if (equipmentChart) {
                equipmentChart.destroy();
            }
            
            // Extract labels and data
            const labels = data.map(item => viewType === 'make' ? item.make : `${item.make} - ${item.model}`);
            const counts = data.map(item => item.count);
            
            // Calculate total for percentage display
            const totalCount = counts.reduce((sum, count) => sum + count, 0);
            
            // Create color palette based on data length
            const colors = generateColorGradient(data.length);
            
            // Create new chart with enhanced features
            equipmentChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Count',
                        data: counts,
                        backgroundColor: colors,
                        borderColor: colors.map(color => color.replace(', 0.7)', ', 1)')),
                        borderWidth: 1,
                        borderRadius: 4,
                        barThickness: viewType === 'model' ? 'flex' : 40
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    animation: {
                        duration: 1000,
                        easing: 'easeOutQuart'
                    },
                    plugins: {
                        legend: {
                            display: false
                        },
                        title: {
                            display: true,
                            text: viewType === 'make' ? 'Equipment Distribution by Manufacturer' : 'Top Equipment by Model',
                            font: {
                                size: 16
                            },
                            padding: {
                                bottom: 15
                            }
                        },
                        tooltip: {
                            callbacks: {
                                title: function(context) {
                                    return context[0].label;
                                },
                                label: function(context) {
                                    const count = context.raw;
                                    const percent = ((count / totalCount) * 100).toFixed(1);
                                    return [
                                        `Quantity: ${count} units`,
                                        `Percentage: ${percent}% of total`
                                    ];
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                precision: 0,
                                color: '#555'
                            },
                            title: {
                                display: true,
                                text: 'Quantity',
                                font: {
                                    weight: 'bold'
                                }
                            },
                            grid: {
                                color: 'rgba(0, 0, 0, 0.05)'
                            }
                        },
                        x: {
                            title: {
                                display: true,
                                text: viewType === 'make' ? 'Manufacturer' : 'Equipment Model',
                                font: {
                                    weight: 'bold'
                                }
                            },
                            ticks: {
                                autoSkip: viewType === 'make' ? false : true,
                                maxRotation: viewType === 'make' ? 0 : 45,
                                minRotation: viewType === 'make' ? 0 : 45,
                                color: '#555',
                                // Truncate long labels
                                callback: function(val, index) {
                                    const label = this.getLabelForValue(val);
                                    if (viewType === 'model' && label.length > 20) {
                                        return label.substring(0, 17) + '...';
                                    }
                                    return label;
                                }
                            }
                        }
                    }
                }
            });
            
            // Add a table below the chart if in model view
            if (viewType === 'model' && data.length > 5) {
                createEquipmentTable(data);
            } else {
                // Remove table if switching back to make view
                const tableContainer = document.getElementById('equipmentTableContainer');
                if (tableContainer) {
                    tableContainer.innerHTML = '';
                }
            }
        }
        
        // Generate a visually distinct color palette
        function generateColorGradient(count) {
            const colors = [];
            
            // If only a few items, use distinct preset colors
            if (count <= 7) {
                return [
                    'rgba(52, 152, 219, 0.7)', // Blue
                    'rgba(155, 89, 182, 0.7)', // Purple
                    'rgba(52, 73, 94, 0.7)',   // Dark blue
                    'rgba(22, 160, 133, 0.7)', // Green
                    'rgba(241, 196, 15, 0.7)', // Yellow
                    'rgba(230, 126, 34, 0.7)', // Orange
                    'rgba(231, 76, 60, 0.7)'   // Red
                ].slice(0, count);
            }
            
            // For many items, create a blue-to-purple gradient
            for (let i = 0; i < count; i++) {
                // Calculate color ratio based on position
                const ratio = i / (count - 1);
                
                // Interpolate between blue and purple
                const r = Math.round(52 + (103 * ratio));
                const g = Math.round(152 - (63 * ratio));
                const b = Math.round(219 - (37 * ratio));
                
                colors.push(`rgba(${r}, ${g}, ${b}, 0.7)`);
            }
            
            return colors;
        }
        
        // Create a detailed table for equipment models
        function createEquipmentTable(data) {
            // Get or create table container
            let tableContainer = document.getElementById('equipmentTableContainer');
            if (!tableContainer) {
                tableContainer = document.createElement('div');
                tableContainer.id = 'equipmentTableContainer';
                tableContainer.className = 'mt-4';
                document.getElementById('equipmentChartContainer').after(tableContainer);
            }
            
            // Calculate total for percentage
            const totalCount = data.reduce((sum, item) => sum + item.count, 0);
            
            // Create table HTML
            tableContainer.innerHTML = `
                <h6 class="mb-3">Detailed Equipment List</h6>
                <div class="table-responsive">
                    <table class="table table-sm table-hover">
                        <thead>
                            <tr>
                                <th>Manufacturer</th>
                                <th>Model</th>
                                <th class="text-end">Quantity</th>
                                <th class="text-end">Percentage</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${data.map(item => `
                                <tr>
                                    <td>${item.make}</td>
                                    <td>${item.model}</td>
                                    <td class="text-end">${item.count}</td>
                                    <td class="text-end">${((item.count / totalCount) * 100).toFixed(1)}%</td>
                                </tr>
                            `).join('')}
                        </tbody>
                        <tfoot>
                            <tr class="table-active fw-bold">
                                <td colspan="2">Total</td>
                                <td class="text-end">${totalCount}</td>
                                <td class="text-end">100%</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            `;
        }
        
        // Update the summary statistics
        function updateSummaryStats(summary) {
            totalStatsEl.innerHTML = `
                <div class="col-4">
                    <h4 class="fs-3">${summary.total_filled + summary.total_vacant}</h4>
                    <div class="text-muted">Total</div>
                </div>
                <div class="col-4">
                    <h4 class="fs-3 text-primary">${summary.total_filled}</h4>
                    <div class="text-muted">Filled</div>
                </div>
                <div class="col-4">
                    <h4 class="fs-3 text-danger">${summary.total_vacant}</h4>
                    <div class="text-muted">Vacant</div>
                </div>
            `;
        }
        
        // Update the positions table
        function updatePositionsTable(positions, summary) {
            positionsTableBody.innerHTML = '';
            
            // Add rows for each position
            positions.forEach(pos => {
                const filledPercent = Math.round((pos.filled / pos.total) * 100);
                const row = document.createElement('tr');
                
                row.innerHTML = `
                    <td>${pos.name}</td>
                    <td class="text-center">${pos.filled}</td>
                    <td class="text-center">${pos.vacant}</td>
                    <td>
                        <div class="d-flex justify-content-between mb-1">
                            <small>${filledPercent}% filled</small>
                            <small>${pos.filled}/${pos.total}</small>
                        </div>
                        <div class="progress" style="height: 8px">
                            <div class="progress-bar bg-primary" role="progressbar" style="width: ${filledPercent}%" 
                                aria-valuenow="${filledPercent}" aria-valuemin="0" aria-valuemax="100"></div>
                        </div>
                    </td>
                `;
                
                positionsTableBody.appendChild(row);
            });
            
            // Add summary row
            const totalPercent = Math.round((summary.total_filled / (summary.total_filled + summary.total_vacant)) * 100);
            positionsTableFoot.innerHTML = `
                <tr class="table-active fw-bold">
                    <td>Total</td>
                    <td class="text-center">${summary.total_filled}</td>
                    <td class="text-center">${summary.total_vacant}</td>
                    <td>
                        <div class="d-flex justify-content-between mb-1">
                            <small>${totalPercent}% filled</small>
                            <small>${summary.total_filled}/${summary.total_filled + summary.total_vacant}</small>
                        </div>
                        <div class="progress" style="height: 8px">
                            <div class="progress-bar bg-primary" role="progressbar" style="width: ${totalPercent}%" 
                                aria-valuenow="${totalPercent}" aria-valuemin="0" aria-valuemax="100"></div>
                        </div>
                    </td>
                </tr>
            `;
        }
        
        // Set up event listeners
        refreshButton.addEventListener('click', () => loadPersonnelData(true));
        
        // View type change handler
        equipmentViewType.addEventListener('change', () => loadEquipmentData(true));
        
        // Initial load
        loadPersonnelData();
        
        // Set up auto-refresh every 60 seconds
        setInterval(() => loadPersonnelData(), 60000);
    });
    </script>
  
</body>
</html>