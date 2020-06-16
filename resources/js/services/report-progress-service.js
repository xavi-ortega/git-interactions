export const ReportProgressService = {
    connectReportProgress(id, onProgress, onEnded) {
        window.Echo.channel(`report-progress-${id}`)
            .listen(".progress.updated", onProgress)
            .listen(".progress.finished", onEnded);
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
