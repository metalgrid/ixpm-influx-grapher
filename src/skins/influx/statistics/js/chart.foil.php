<script src="<?= asset('influx/js/chartjs-4.4.4.js') ?>"></script>
<script>
    function formatBitsToSI(bitsPerSecond) {
        const units = ['bps', 'Kbps', 'Mbps', 'Gbps', 'Tbps', 'Pbps'];
        let index = 0;

        // Scale the value down to the appropriate unit
        while (bitsPerSecond >= 1000 && index < units.length - 1) {
            bitsPerSecond /= 1000;
            index++;
        }

        return `${bitsPerSecond.toFixed(2)} ${units[index]}`;
    }

    function loadChart(id, url) {
        console.log("loading chart", id, "from", url);
        fetch(url).then(res => {
            res.json().then((data) => drawChart(id, data));
        }, rej => console.error);
    }

    function setupObserver() {
        const observer = new IntersectionObserver((entries, observer) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const chartId = entry.target.id;
                    const url = entry.target.getAttribute("data-url")
                    loadChart(chartId, url);

                    // Optionally, unobserve the element after loading the chart to avoid multiple triggers
                    observer.unobserve(entry.target);
                }
            });
        }, {
            root: null, // Observe in the viewport
            rootMargin: '0px', // Optional: adjust margins for trigger points
            threshold: 0.3 // Trigger when 30% of the element is visible
        });

        document.querySelectorAll('.chart').forEach(chart => {
            observer.observe(chart);
        });
    }

    // Function to render the chart using Chart.js
    async function drawChart(id, chartData) {
        const ctx = document.getElementById(id).getContext("2d");
        const myChart = new Chart(ctx, {
            type: "line", // Can be 'bar' for a stacked bar chart
            data: chartData,
            options: {
                elements: {
                    point: {
                        radius: 0
                    }
                },
                scales: {
                    x: {
                        stacked: true
                    }, // Stack x-axis
                    y: {
                        stacked: true,
                        ticks: {
                            callback: function(value, index, values) {
                                return formatBitsToSI(value);
                            }
                        }
                    }, // Stack y-axis
                },
                plugins: {
                    tooltip: {
                        mode: 'index',
                        axis: 'x',
                        intersect: false,
                        callbacks: {
                            label: function(context) {
                                return formatBitsToSI(context.parsed.y);
                            }
                        }
                    },
                },
                hover: {
                    mode: 'index',
                    intersect: false,
                },
            },
        });
    }

    // Call the drawChart function to render the chart
    // drawChart();
    setupObserver();
</script>