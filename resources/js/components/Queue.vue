<template>
  <div class="queue">
    <div class="container">
      <div class="box" :style="{visibility: queue[0] ? 'visible' : 'hidden'}">
        <div class="box-progress" :class="progressClass"></div>
        <span
          :class="{ light: queue[0] && queue[0].type > 2 }"
        >{{ queue[0] && queue[0].report.repository.slug }}</span>
      </div>
      <div class="box" :style="{visibility: queue[1] ? 'visible' : 'hidden'}">
        <span>{{ queue[1] && queue[1].report.repository.slug }}</span>
      </div>
      <div class="box" :style="{visibility: queue[2] ? 'visible' : 'hidden'}">
        <span>{{ queue[2] && queue[2].report.repository.slug }}</span>
      </div>
      <div class="box" :style="{visibility: queue[3] ? 'visible' : 'hidden'}">
        <span>{{ queue[3] && queue[3].report.repository.slug }}</span>
      </div>
    </div>
  </div>
</template>

<script>
const WAITING = 0;
const FETCHING_ISSUES = 1;
const FETCHING_PULL_REQUESTS = 2;
const FETCHING_CONTRIBUTORS = 3;
const FETCHING_CODE = 4;

export default {
  mounted() {},

  props: {
    queue: {
      default: () => []
    }
  },

  computed: {
    progressClass() {
      return {
        h25: this.queue[0] && this.queue[0].type === FETCHING_PULL_REQUESTS,
        h50: this.queue[0] && this.queue[0].type === FETCHING_CONTRIBUTORS,
        h75: this.queue[0] && this.queue[0].type === FETCHING_CODE,
        h100:
          this.queue[0] &&
          this.queue[0].type === FETCHING_CODE &&
          this.queue[0].progress >= 99
      };
    }
  }
};
</script>
