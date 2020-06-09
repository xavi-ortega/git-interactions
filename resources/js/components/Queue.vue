<template>
    <div class="queue">
        <ul>
            <li v-for="job in queue" :key="job.report.id">
                {{ job.report.repository.slug }}
            </li>
        </ul>
    </div>
</template>

<script>
export default {
    mounted() {
        axios.get("/report/queue").then(response => {
            this.queue = response.data;
        });
    },

    data() {
        return {
            queue: []
        };
    }
};
</script>
