<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is logged in and is an admin
if (!isLoggedIn() || $_SESSION['role'] !== 'admin') {
    redirect('../auth/login.php');
}

$database = Database::getInstance();
$pdo = $database->getConnection();

// Get analytics data
$analytics = [];

try {
    // Monthly user registrations (last 12 months)
    $monthly_users = $pdo->query("
        SELECT 
            DATE_FORMAT(created_at, '%Y-%m') as month,
            COUNT(*) as count,
            role
        FROM users 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
        GROUP BY DATE_FORMAT(created_at, '%Y-%m'), role
        ORDER BY month ASC
    ")->fetchAll(PDO::FETCH_ASSOC);

    // Property statistics by status
    $property_stats = $pdo->query("
        SELECT status, COUNT(*) as count 
        FROM properties 
        GROUP BY status
    ")->fetchAll(PDO::FETCH_ASSOC);

    // Monthly revenue (last 12 months)
    $monthly_revenue = $pdo->query("
        SELECT 
            DATE_FORMAT(updated_at, '%Y-%m') as month,
            SUM(price) as revenue
        FROM properties 
        WHERE status = 'sold' 
        AND updated_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
        GROUP BY DATE_FORMAT(updated_at, '%Y-%m')
        ORDER BY month ASC
    ")->fetchAll(PDO::FETCH_ASSOC);

    // Appointment trends
    $appointment_trends = $pdo->query("
        SELECT 
            DATE_FORMAT(created_at, '%Y-%m') as month,
            COUNT(*) as count,
            status
        FROM appointments 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
        GROUP BY DATE_FORMAT(created_at, '%Y-%m'), status
        ORDER BY month ASC
    ")->fetchAll(PDO::FETCH_ASSOC);

    // Top performing agents
    $top_agents = $pdo->query("
        SELECT 
            CONCAT(u.first_name, ' ', u.last_name) as agent_name,
            a.agency_name,
            COUNT(p.id) as properties_sold,
            COALESCE(SUM(p.price), 0) as total_revenue
        FROM users u
        JOIN agents a ON u.id = a.user_id
        LEFT JOIN properties p ON u.id = p.agent_id AND p.status = 'sold'
        WHERE u.role = 'agent'
        GROUP BY u.id, u.first_name, u.last_name, a.agency_name
        ORDER BY properties_sold DESC, total_revenue DESC
        LIMIT 10
    ")->fetchAll(PDO::FETCH_ASSOC);

    $analytics = [
        'monthly_users' => $monthly_users,
        'property_stats' => $property_stats,
        'monthly_revenue' => $monthly_revenue,
        'appointment_trends' => $appointment_trends,
        'top_agents' => $top_agents
    ];

} catch (Exception $e) {
    error_log("Analytics error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analytics Dashboard - Admin Panel</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <style>
        :root {
            --admin-primary: #dc3545;
            --admin-secondary: #c82333;
            --admin-dark: #721c24;
            --admin-light: #f8d7da;
            --admin-bg: #f8f9fa;
        }

        body {
            background: var(--admin-bg);
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }

        .admin-header {
            background: #000;
            color: white;
            padding: 1rem 0;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
        }

        .analytics-card {
            background: white;
            border-radius: 1rem;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            padding: 1.5rem;
            margin-bottom: 2rem;
            border: 1px solid #e9ecef;
        }

        .chart-container {
            position: relative;
            height: 400px;
            margin-top: 1rem;
        }

        .metric-card {
            background: linear-gradient(135deg, var(--admin-primary) 0%, var(--admin-secondary) 100%);
            color: white;
            border-radius: 1rem;
            padding: 1.5rem;
            text-align: center;
            margin-bottom: 1rem;
        }

        .metric-value {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .metric-label {
            font-size: 0.875rem;
            opacity: 0.9;
        }

        .agent-rank {
            display: flex;
            align-items: center;
            padding: 1rem;
            border: 1px solid #e9ecef;
            border-radius: 0.5rem;
            margin-bottom: 0.5rem;
            transition: all 0.3s ease;
        }

        .agent-rank:hover {
            background: #f8f9fa;
            transform: translateX(5px);
        }

        .rank-number {
            width: 40px;
            height: 40px;
            background: var(--admin-primary);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            margin-right: 1rem;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <nav class="navbar navbar-expand-lg admin-header">
        <div class="container-fluid">
            <a class="navbar-brand d-flex align-items-center ms-3" href="../pages/home.php">
                <img src="../assets/images/logo1.png" alt="Logo" width="120" height="50" class="me-3">
            </a>
            
            <div class="d-flex align-items-center me-3">
                <a href="dashboard.php" class="btn btn-light">
                    <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
                </a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <!-- Page Header -->
        <div class="analytics-card">
            <h1 class="h4 mb-0">
                <i class="fas fa-chart-bar me-2"></i>Analytics Dashboard
            </h1>
            <p class="text-muted mb-0">Comprehensive insights into your real estate platform</p>
        </div>

        <!-- Key Metrics -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="metric-card">
                    <div class="metric-value">
                        <?= array_sum(array_column($analytics['property_stats'], 'count')) ?>
                    </div>
                    <div class="metric-label">Total Properties</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="metric-card">
                    <div class="metric-value">
                        €<?= number_format(array_sum(array_column($analytics['monthly_revenue'], 'revenue')) / 1000, 0) ?>K
                    </div>
                    <div class="metric-label">Total Revenue</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="metric-card">
                    <div class="metric-value">
                        <?= count(array_filter($analytics['top_agents'], function($agent) { return $agent['properties_sold'] > 0; })) ?>
                    </div>
                    <div class="metric-label">Active Agents</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="metric-card">
                    <div class="metric-value">
                        <?= array_sum(array_column($analytics['appointment_trends'], 'count')) ?>
                    </div>
                    <div class="metric-label">Total Appointments</div>
                </div>
            </div>
        </div>

        <!-- Charts Row -->
        <div class="row">
            <!-- User Growth Chart -->
            <div class="col-lg-6">
                <div class="analytics-card">
                    <h5><i class="fas fa-users me-2"></i>User Growth Trend</h5>
                    <div class="chart-container">
                        <canvas id="userGrowthChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Property Status Chart -->
            <div class="col-lg-6">
                <div class="analytics-card">
                    <h5><i class="fas fa-home me-2"></i>Property Distribution</h5>
                    <div class="chart-container">
                        <canvas id="propertyStatusChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Revenue and Top Agents Row -->
        <div class="row">
            <!-- Monthly Revenue -->
            <div class="col-lg-8">
                <div class="analytics-card">
                    <h5><i class="fas fa-euro-sign me-2"></i>Monthly Revenue Trend</h5>
                    <div class="chart-container">
                        <canvas id="revenueChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Top Performing Agents -->
            <div class="col-lg-4">
                <div class="analytics-card">
                    <h5><i class="fas fa-trophy me-2"></i>Top Performing Agents</h5>
                    <div style="max-height: 400px; overflow-y: auto;">
                        <?php foreach (array_slice($analytics['top_agents'], 0, 5) as $index => $agent): ?>
                            <div class="agent-rank">
                                <div class="rank-number"><?= $index + 1 ?></div>
                                <div>
                                    <h6 class="mb-1"><?= htmlspecialchars($agent['agent_name']) ?></h6>
                                    <small class="text-muted"><?= htmlspecialchars($agent['agency_name']) ?></small>
                                    <div class="mt-1">
                                        <small class="text-success">
                                            <i class="fas fa-home me-1"></i><?= $agent['properties_sold'] ?> sales
                                        </small>
                                        <small class="text-primary ms-2">
                                            <i class="fas fa-euro-sign me-1"></i><?= number_format($agent['total_revenue'], 0, ',', ' ') ?>
                                        </small>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // User Growth Chart
        const userGrowthCtx = document.getElementById('userGrowthChart').getContext('2d');
        const userGrowthChart = new Chart(userGrowthCtx, {
            type: 'line',
            data: {
                labels: <?= json_encode(array_unique(array_column($analytics['monthly_users'], 'month'))) ?>,
                datasets: [{
                    label: 'New Users',
                    data: <?= json_encode(array_column($analytics['monthly_users'], 'count')) ?>,
                    borderColor: '#dc3545',
                    backgroundColor: 'rgba(220, 53, 69, 0.1)',
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                }
            }
        });

        // Property Status Chart
        const propertyStatusCtx = document.getElementById('propertyStatusChart').getContext('2d');
        const propertyStatusChart = new Chart(propertyStatusCtx, {
            type: 'doughnut',
            data: {
                labels: <?= json_encode(array_column($analytics['property_stats'], 'status')) ?>,
                datasets: [{
                    data: <?= json_encode(array_column($analytics['property_stats'], 'count')) ?>,
                    backgroundColor: ['#28a745', '#dc3545', '#ffc107', '#17a2b8']
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false
            }
        });

        // Revenue Chart
        const revenueCtx = document.getElementById('revenueChart').getContext('2d');
        const revenueChart = new Chart(revenueCtx, {
            type: 'bar',
            data: {
                labels: <?= json_encode(array_column($analytics['monthly_revenue'], 'month')) ?>,
                datasets: [{
                    label: 'Revenue (€)',
                    data: <?= json_encode(array_column($analytics['monthly_revenue'], 'revenue')) ?>,
                    backgroundColor: 'rgba(220, 53, 69, 0.8)',
                    borderColor: '#dc3545',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return '€' + value.toLocaleString();
                            }
                        }
                    }
                }
            }
        });
    </script>
</body>
</html>
