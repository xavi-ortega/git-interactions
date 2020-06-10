export const AuthService = {
    async login(credentials) {
        return axios.post("/login", credentials);
    },

    async register(data) {
        return axios.post("/register", data);
    }
};
