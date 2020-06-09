<template>
    <canvas ref="chart"></canvas>
</template>

<script>
import * as Chart from "chart.js";

export default {
    mounted() {
        const canvas = this.$refs.chart;

        this.instance = new Chart(canvas, {
            type: "doughnut",
            data: {
                datasets: [this.dataset],

                labels: this.labels
            },
            options: {
                aspectRatio: 2,
                rotation: 3,
                cutoutPercentage: 60
                // legend: {
                //     display: false
                // }
            }
        });
    },

    data() {
        return {
            instance: {}
        };
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
