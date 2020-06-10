export const ReportProgressService = {
    connectReportProgress(id, callback) {
        return window.Echo.channel(`report-progress-${id}`).listen(
            ".progress.updated",
            callback
        );
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
