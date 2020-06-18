<template>
  <div class="wrapper">
    <div class="row">
      <div class="col">
        <h2 class="text-center">{{ report.repository.slug }}</h2>
      </div>
    </div>

    <div class="row">
      <div class="col-6 offset-3">
        <div class="progress mt-5 mb-4">
          <div
            class="progress-ball"
            :style="progressStyle"
            :aria-valuenow="report.progress.progress"
            aria-valuemin="0"
            aria-valuemax="100"
          ></div>

          <div class="progress-bar" :style="{ width: report.progress.progress + '%'}"></div>
        </div>
        <p class="text-center">{{ feedback }}</p>
      </div>
    </div>

    <div class="row">
      <queue :queue="queue"></queue>
    </div>
  </div>
</template>

<script>
import { ReportProgressService } from "../services/report-progress-service";
import { ReportService } from "../services/report-service";

const WAITING = 0;
const FETCHING_ISSUES = 1;
const FETCHING_PULL_REQUESTS = 2;
const FETCHING_CONTRIBUTORS = 3;
const FETCHING_CODE = 4;

export default {
  mounted() {
    ReportService.fetchQueue().then(response => {
      this.queue = response.data;
    });

    ReportProgressService.connectQueue(({ queue }) => {
      console.log("WS -> queue updated");
      this.queue = queue;
    });

    ReportProgressService.connectReportProgress(
      this.report.id,
      this.onReportProgress,
      this.onReportEnded,
      this.onReportFailed
    );
  },

  data() {
    return {
      queue: []
    };
  },

  props: {
    report: {
      default: () => ({
        repository: {},
        pogress: {}
      }),
      required: false
    }
  },

  methods: {
    onReportProgress({ progress }) {
      console.log("WS -> progress updated");

      this.report.progress = progress;
    },

    onReportEnded() {
      console.log("WS -> progress ended");
      ReportProgressService.disconnectReportProgress(this.report.id);

      this.$store.dispatch("fetchReport", { id: this.report.id });
    },

    onReportFailed() {
      console.err("WE -> report failed");
      ReportProgressService.disconnectReportProgress(this.report.id);

      this.$router.push({ name: "Home" });
    }
  },

  computed: {
    feedback() {
      switch (this.report.progress.type) {
        case WAITING:
          return `Waiting. Queue position: ${this.queuePosition}`;

        case FETCHING_ISSUES:
          return "Fetching issues...";

        case FETCHING_PULL_REQUESTS:
          return "Fetching pull requests...";

        case FETCHING_CONTRIBUTORS:
          return "Fetching contributors...";

        case FETCHING_CODE:
          return "Fetching commits...";

        default:
          return "";
      }
    },

    queuePosition() {
      const position = this.queue.findIndex(
        report => report.id === this.report.id
      );

      if (position > 0) {
        return position;
      } else {
        return 0;
      }
    },

    progressStyle() {
      const right = 100 - this.report.progress.progress;
      return {
        right: `${right}%`
      };
    }
  }
};
</script>

<style lang="scss" scoped>
.wrapper {
  height: 60vh;
  display: flex;
  flex-direction: column;
  justify-content: center;
}
</style>
