export default {
    fetchReport({ commit }, { id }) {
        return axios.get(`/report/${id}`).then(({ data }) => {
            commit("addReport", data);
        });
    }
};
