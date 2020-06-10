import { ReportService } from "../../services/report-service";

export default {
    fetchReport({ commit }, { id }) {
        return ReportService.fetchById(id).then(({ data }) => {
            commit("addReport", data);
        });
    }
};
