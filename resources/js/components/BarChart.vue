<template>
    <canvas ref="chart"></canvas>
</template>

<script>
import * as Chart from "chart.js";

export default {
    mounted() {
        const canvas = this.$refs.chart;

        const chart = new Chart(canvas, {
            type: "bar",
            data: {
                datasets: [
                    {
                        barThickness: "flex",
                        ...this.dataset
                    }
                ],
                labels: this.labels
            },
            options: {
                legend: {
                    display: false
                }
            }
        });
    },

    props: {
        dataset: {
            default: () => ({})
        },

        labels: {
            default: () => []
        }
    }
};
</script>
