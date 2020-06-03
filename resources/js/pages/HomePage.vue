<template>
    <div class="row justify-content-center">
        <div class="col-md-6">
            <shell title="My searches">
                <router-link to="/report">Report</router-link>

                <ul v-for="report in lastUserReports" :key="report.id">
                    <li>{{ report.repository.slug }}</li>
                </ul>
            </shell>
        </div>

        <div class="col-md-6">
            <shell title="Popular searches"></shell>
        </div>
    </div>
</template>

<script>
export default {
    data: function() {
        return {
            lastUserReports: []
        };
    },
    mounted() {
        axios
            .get("/user/lastReports")
            .then(reports => {
                this.lastUserReports = reports;
            })
            .catch(err => {
                console.log(err);
            });
    }
};
</script>
