<template>
  <div class="card shell">
    <div class="card-header">
      <h2 class="card-title">{{ title }}</h2>
    </div>

    <div class="card-body">
      <slot></slot>
    </div>
  </div>
</template>

<script lang="ts">
export default {
  props: ["title"]
};
</script>
