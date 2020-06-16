<template>
  <div class="dropdown mr-2">
    <button
      class="btn btn-secondary dropdown-toggle"
      type="button"
      id="dropdownMenuButton"
      data-toggle="dropdown"
      aria-haspopup="true"
      aria-expanded="false"
    >
      <i class="fa fa-bell"></i>
    </button>
    <div class="dropdown-menu dropdown-menu-right" aria-labelledby="dropdownMenuButton">
      <button
        href="#"
        class="btn-link dropdown-item"
        v-for="notification in notifications"
        :key="notification.id"
        @click="visit(notification)"
      >
        <i class="fa fa-eye" v-if="!notification.read_at"></i>
        <i class="fa fa-eye-closed" v-else></i>
        {{ notification.data.message }}
      </button>
    </div>
  </div>
</template>

<script>
import { NotificationService } from "../services/notification-service";
import { mapGetters } from "vuex";
export default {
  mounted() {
    const user = this.getUser;

    NotificationService.fetchAll().then(
      ({ data }) => (this.notifications = data)
    );

    NotificationService.listen(user, notification => {
      console.log("WS -> notification", notification);
      this.notifications = [...this.notifications, notification];
    });
  },

  data() {
    return {
      notifications: []
    };
  },

  methods: {
    visit(notification) {
      this.$router.push({ path: `/${notification.data.url}` });
      NotificationService.visit(notification.id);
    }
  },

  computed: { ...mapGetters(["getUser"]) }
};
</script>
