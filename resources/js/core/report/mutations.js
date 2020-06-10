export default {
    addReport(state, report) {
        state.reports = {
            ...state.reports,
            [report.report.id]: {
                ...report.report,
                ...report
            }
        };

        delete state.reports[report.report.id].report;
    }
};
