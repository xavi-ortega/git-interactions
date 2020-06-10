import { AuthService } from "../../services/auth-service";

export default {
    login({ commit }, credentials) {
        return AuthService.login(credentials).then(({ data }) => {
            commit("setUserData", data);
        });
    },

    register({ commit }, data) {
        return AuthService.register(data).then(({ data }) => {
            commit("setUserData", data);
        });
    },

    logout({ commit }) {
        commit("clearUserData");
    }
};
