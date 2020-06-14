export const NotificationService = {
    fetchAll() {
        return axios.get("user/notifications");
    },

    visit(id) {
        return axions.post(`user/notifications/${id}`);
    },

    listen(user, callback) {
        return Echo.private(`App.User.${user.id}`).notification(callback);
    }
};
