import router from "../router";

export const AuthService = {
    check() {
        const userInfo = localStorage.getItem("user");

        if (userInfo) {
            const userData = JSON.parse(userInfo);

            return userData;
        }

        return false;
    },

    async login(credentials) {
        return axios.post("/login", credentials);
    },

    async register(data) {
        return axios.post("/register", data);
    },

    logout() {
        router.go();
    }
};
