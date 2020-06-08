export default {
    addReport(state, report) {
        state.reports = {
            ...state.reports,
            [report.report.id]: {
                ...report
            }
        };
    }
};
