<template>
  <div class="row justify-content-center">
    <div class="col-md-6">
      <shell title="My searches">
        <template v-if="isLogged">
          <ul v-if="lastUserReports.length">
            <li v-for="report in lastUserReports" :key="report.id">
              <router-link
                :to="{ name: 'Report', params: { id: report.id }}"
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
      .then(({ data }) => {
        this.lastUserReports = data;
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
