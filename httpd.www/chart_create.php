<?php

// Check if AnswerId is provided
if (!isset($_GET['AnswerId'])) {
    die("No AnswerId provided.");
}

require 'db_connect.php';

$AnswerId = intval($_GET['AnswerId']);

// Fetch personality percentages from database
$stmt = $conn->prepare("
    SELECT ExtroversionPercent, SensingPercent, ThinkingPercent, JudgingPercent
    FROM SurveyHeaders 
    WHERE AnswerId = ?
");
$stmt->bind_param("i", $AnswerId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("No data found for AnswerId: " . htmlspecialchars($AnswerId));
}

$row = $result->fetch_assoc();
$stmt->close();
$conn->close();

// Adjust percentages to range from -100 to +100
$adjustedData = [
    ($row['ExtroversionPercent'] - 50) * 2,
    ($row['SensingPercent'] - 50) * 2,
    ($row['ThinkingPercent'] - 50) * 2,
    ($row['JudgingPercent'] - 50) * 2
];

$barColors = ['#FF9C00', '#00B100', '#00E2EF', '#4F57FF'];

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Personality Chart</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.8/dist/chart.umd.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2"></script>
    <style>
        body {
            font-family: Tahoma, sans-serif;
            background-color: #F8F9FA;
            padding: 30px;
            text-align: center;
        }
        .chart-container {
            max-width: 600px;
            margin: 0 auto;
        }
    </style>
</head>
<body>

<div class="chart-container">
    <canvas id="personalityChart"></canvas>
</div>

<script>
    // Ensure dataValues is not redeclared
    if (typeof window.dataValues === 'undefined') {
        window.dataValues = <?php echo json_encode($adjustedData); ?>;
    }

    if (typeof window.chartInstance !== 'undefined') {
        window.chartInstance.destroy(); // Destroy existing chart before creating a new one
    }

    function createChart() {
        const data = {
            labels: ['', '', '', ''],
            datasets: [{
                data: window.dataValues, // Use global variable
                backgroundColor: <?php echo json_encode($barColors); ?>,
                borderRadius: 10
            }]
        };

        const config = {
            type: 'bar',
            data: data,
            options: {
                indexAxis: 'y',
                responsive: true,
                animation: false, // Disables animation

                scales: {
                    x: {
                        min: -100,
                        max: 100,
                        grid: { 
                            display: true,
                            color: 'rgba(33, 74, 129, 0.5)',
                            lineWidth: 2,       // Sets grid lines thickness
                            borderColor: 'rgba(33, 74, 129, 0.5)', // Matches gridline color
                            borderWidth: 2      // Sets axis border thickness
                        },
                        ticks: { display: false }
                    },
                    y: {
                        display: false
                    }
                }
                ,
                plugins: {
                    legend: { display: false },
                    tooltip: { enabled: false },
                    datalabels: {
                        color: function(context) {
                            const value = context.dataset.data[context.dataIndex];
                            let anchor;
                            if (value > 15) {
                                anchor = 'center';
                            } else if (value >= 0 && value <= 15) {
                                anchor = 'end';
                            } else if (value < 0 && value >= -15) {
                                anchor = 'start';
                            } else {
                                anchor = 'center';
                            }
                            // For the second (index 1) and fourth (index 3) bar, if the label is centered, use white text
                            if ((context.dataIndex === 1 || context.dataIndex === 3) && anchor === 'center') {
                                return 'white';
                            }
                            return 'black';
                        },
                        font: {
                            family: 'Tahoma',
                            size: 14
                        },
                        formatter: (value) => Math.abs(value) + '%',
                        anchor: (context) => {
                            const value = context.dataset.data[context.dataIndex];
                            if (value > 15) {
                                return 'center';
                            } else if (value >= 0 && value <= 15) {
                                return 'end';
                            } else if (value < 0 && value >= -15) {
                                return 'start';
                            } else {
                                return 'center';
                            }
                        },
                        align: (context) => {
                            const value = context.dataset.data[context.dataIndex];
                            if (value > 15) {
                                return 'center';
                            } else if (value >= 0 && value <= 15) {
                                return 'right';
                            } else if (value < 0 && value >= -15) {
                                return 'left';
                            } else {
                                return 'center';
                            }
                        }
                    }
                }
            },
            plugins: [ChartDataLabels]
        };

        const ctx = document.getElementById('personalityChart');
        if (ctx) {
            window.chartInstance = new Chart(ctx, config);
        } else {
            console.error("Chart canvas not found!");
        }
    }

    // Create chart
    createChart();

    // Function to send chart image to server
    function saveChartToServer() {
        const chartCanvas = document.getElementById('personalityChart');

        if (!chartCanvas) {
            console.error('Chart canvas not found!');
            return;
        }

        // Wait for rendering to complete
        setTimeout(() => {
            const imageData = chartCanvas.toDataURL('image/png');

            fetch('save_chart.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    AnswerId: <?php echo json_encode($AnswerId); ?>,
                    imageData: imageData
                })
            })
            .then(response => response.text())
                .then(result => {
                    console.log('✅ Image saved successfully:', result);
                    // Uncomment the following lines if you want to redirect after saving
                    
                    setTimeout(() => {
                        window.location.href = "https://proanalys.se/phpWord_short.php?AnswerId=" + <?php echo json_encode($AnswerId); ?>;
                    }, 500); // Small delay before redirecting
                    
                })
            .catch(error => {
                console.error('❌ Error saving image:', error);
            });
        }, 1500); // 1.5s delay to ensure rendering
    }

    // Delay slightly to ensure rendering is complete
    setTimeout(saveChartToServer, 1000);
</script>

</body>
</html>
