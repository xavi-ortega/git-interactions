export const ReportProgressService = {
    connectReportProgress(id, onProgress, onEnded) {
        return window.Echo.channel(`report-progress-${id}`)
            .listen(".progress.updated", onProgress)
            .listen(".progress.finished", onEnded);
    },

    disconnectReportProgress(id) {
        window.Echo.leaveChannel(`report-progress-${id}`);
    },

    connectQueue(callback) {
        return window.Echo.channel("general-queue").listen(
            ".queue.updated",
            callback
        );
    },

    disconnectQueue() {
        window.Echo.leaveChannel("general-queue");
    }
};
