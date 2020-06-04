export default {
    login({ commit }, credentials) {
        return axios.post("/login", credentials).then(({ data }) => {
            commit("setUserData", data);
        });
    },

    register({ commit }, data) {
        return axios.post("/register", data).then(({ data }) => {
            commit("setUserData", data);
        });
    },

    logout({ commit }) {
        commit("clearUserData");
    }
};
