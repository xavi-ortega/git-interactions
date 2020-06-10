export const ReportService = {
    async fetchQueue() {
        return axios.get("/report/queue");
    },

    async fetchById(id) {
        return axios.get(`/report/${id}`);
    },

    async fetchLast() {
        return axios.get("/user/lastReports");
    }
};
