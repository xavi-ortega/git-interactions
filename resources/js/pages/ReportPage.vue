<template>
  <div class="row justify-content-center">
    <div class="col-md-4">
      <shell title="Report">Test</shell>
    </div>
  </div>
</template>

<script>
import { mapGetters } from "vuex";
import store from "../core";

export default {
  mounted() {},

  data() {
    return {};
  },

  computed: {
    report() {
      const id = this.$route.params.id;

      return this.$store.getters.getReportById(id);
    }
  },

  beforeRouteEnter(to, from, next) {
    const report = store.getters.getReportById(to.params.id);

    if (!report) {
      store.dispatch("fetchReport", { id: to.params.id });
    }

    next();
  }
};
</script>
