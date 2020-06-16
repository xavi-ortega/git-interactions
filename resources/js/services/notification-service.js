export const NotificationService = {
    fetchAll() {
        return axios.get("user/notifications");
    },

    visit(id) {
        return axios.post(`user/notifications/${id}`);
    },

    listen(user, callback) {
        Echo.private(`App.User.${user.id}`).notification(callback);
    }
};
