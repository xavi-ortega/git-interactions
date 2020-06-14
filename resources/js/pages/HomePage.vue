<template>
  <div class="container">
    <div class="row justify-content-center">
      <div class="col-md-6">
        <shell title="My searches">
          <template v-if="isLogged">
            <ul v-if="lastUserReports.length">
              <li v-for="report in lastUserReports" :key="report.id">
                <router-link
                  :to="{
                                        name: 'Report',
                                        params: {
                                            name: report.repository.name,
                                            owner: report.repository.owner,
                                            id: report.id
                                        }
                                    }"
                >{{ report.repository.slug }}</router-link>
              </li>
            </ul>
          </template>

          <div class="alert alert-warning" v-else>
            You have to be a member to check for your reports.
            <br />
            <button class="btn btn-light" @click="register">Register</button>
          </div>
        </shell>
      </div>

      <div class="col-md-6">
        <shell title="Popular searches">
          <ul v-if="popularReports.length">
            <li v-for="search in popularReports" :key="search.id">
              <router-link
                :to="{
                                    name: 'Report',
                                    params: {
                                        name: search.repository.name,
                                        owner: search.repository.owner,
                                        id: search.repository.latest_report.id
                                    }
                                }"
              >{{ search.repository.slug }}</router-link>
            </li>
          </ul>
        </shell>
      </div>
    </div>
  </div>
</template>

<script>
import { mapGetters } from "vuex";
import { ReportService } from "../services/report-service";

export default {
  data: function() {
    return {
      lastUserReports: [],
      popularReports: []
    };
  },
  methods: {
    register() {
      this.$router.push({ name: "Register" });
    }
  },
  mounted() {
    ReportService.fetchLast()
      .then(({ data }) => {
        this.lastUserReports = data;
      })
      .catch(err => {
        console.log(err);
      });

    ReportService.fetchPopular()
      .then(({ data }) => {
        this.popularReports = data;
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
