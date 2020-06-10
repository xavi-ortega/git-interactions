<template>
    <div class="wrapper">
        <div class="row">
            <div class="col">
                <h2 class="text-center my-5">{{ report.repository.slug }}</h2>
            </div>
        </div>

        <div class="row">
            <div class="col-6 offset-3">
                <div class="progress my-2">
                    <div
                        class="progress-bar"
                        :style="{ width: report.progress.value + '%' }"
                        :aria-valuenow="report.progress.value"
                        aria-valuemin="0"
                        aria-valuemax="100"
                    ></div>
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
            ({ progress }) => {
                console.log("WS -> progress updated");
                this.report.progress = progress;
            }
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
    computed: {
        feedback() {
            switch (this.report.progress.type) {
                case WAITING:
                    return `Waiting. Queue position: ${this.queue.findIndex(
                        report => report.id === this.report.id
                    )}`;

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
