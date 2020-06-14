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
      <a
        href="#"
        class="dropdown-item"
        v-for="notification in notifications"
        :key="notification.id"
        @click="visit(notification)"
      >
        <i class="fa fa-eye" v-if="!notification.read_at"></i>
        <i class="fa fa-eye-closed" v-else></i>
        {{ notification.data.message }}
      </a>
    </div>
  </div>
</template>

<script>
import { NotificationService } from "../services/notification-service";
export default {
  mounted() {
    NotificationService.fetchAll().then(
      ({ data }) => (this.notifications = data)
    );

    NotificationService.listen(notification => {
      console.log("WS -> notification");
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
  }
};
</script>
