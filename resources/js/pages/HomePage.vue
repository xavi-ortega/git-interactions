<template>
    <div class="row justify-content-center">
        <div class="col-md-6">
            <shell title="My searches">
                <router-link to="/report">Report</router-link>
                <template v-if="isLogged">
                    <ul v-for="report in lastUserReports" :key="report.id">
                        <li>{{ report.repository.slug }}</li>
                    </ul>
                </template>

                <div class="alert alert-warning" v-else>
                    You have to be a member to check for your reports. <br />
                    <button class="btn btn-light" @click="register">
                        Register
                    </button>
                </div>
            </shell>
        </div>

        <div class="col-md-6">
            <shell title="Popular searches"></shell>
        </div>
    </div>
</template>

<script>
import { mapGetters } from "vuex";

export default {
    data: function() {
        return {
            lastUserReports: []
        };
    },
    methods: {
        register() {
            this.$router.push({ name: "Register" });
        }
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
    },
    computed: {
        ...mapGetters(["isLogged"])
    }
};
</script>
