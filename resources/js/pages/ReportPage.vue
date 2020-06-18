<template>
  <div>
    <div class="container-fluid" v-if="inProgress()">
      <loading-report :report="report"></loading-report>
    </div>

    <div class="container" v-else-if="isFinished()">
      <report :report="report"></report>
    </div>

    <div class="container" v-else-if="isFailed()">
      <failed-report></failed-report>
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
      return this.report && this.report.status === "loading";
    },

    isFailed() {
      return this.report && this.report.status === "failed";
    },

    isFinished() {
      return this.report && this.report.status === "finished";
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
