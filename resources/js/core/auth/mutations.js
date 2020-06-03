export default {
    setUserData(state, userData) {
        state.auth = userData;
        localStorage.setItem("user", JSON.stringify(userData));
        axios.defaults.headers.common.Authorization = `Bearer ${userData.token}`;
    },

    clearUserData() {
        localStorage.removeItem("user");
    }
};
