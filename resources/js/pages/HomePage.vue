<template>
    <div class="row justify-content-center">
        <div class="col-md-6">
            <shell title="My searches">
                <router-link to="/report">Report</router-link>
            </shell>
        </div>

        <div class="col-md-6">
            <shell title="Popular searches"></shell>
        </div>
    </div>
</template>

<script lang="ts">
export default {
    data: function() {
        return {};
    }
};
</script>
