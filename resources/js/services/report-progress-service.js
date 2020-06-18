export const ReportProgressService = {
    connectReportProgress(id, onProgress, onEnded, onFailed) {
        window.Echo.channel(`report-progress-${id}`)
            .listen(".progress.updated", onProgress)
            .listen(".progress.finished", onEnded)
            .listen(".progress.failed", onFailed);
    },

    disconnectReportProgress(id) {
        window.Echo.leaveChannel(`report-progress-${id}`);
    },

    connectQueue(callback) {
        window.Echo.channel("general-queue").listen(".queue.updated", callback);
    },

    disconnectQueue() {
        window.Echo.leaveChannel("general-queue");
    }
};
