<template>
  <div>
    <div class="container-fluid" v-if="inProgress()">
      <loading-report :report="report"></loading-report>
    </div>

    <div class="container" v-if="report && !inProgress()">
      <report :report="report"></report>
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

  methods: {
    inProgress() {
      return this.report && this.report.progress;
    }
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
  },

  beforeRouteUpdate(to, from, next) {
    const report = store.getters.getReportById(to.params.id);

    if (!report) {
      store.dispatch("fetchReport", { id: to.params.id });
    }

    next();
  }
};
</script>
