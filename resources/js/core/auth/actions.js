export default {
    login({ commit }, credentials) {
        return axios.post("/login", credentials).then(({ data }) => {
            commit("setUserData", data);
        });
    },

    logout({ commit }) {
        commit("clearUserData");
    }
};
