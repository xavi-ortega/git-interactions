<template>
    <nav class="navbar navbar-expand-md navbar-light bg-white shadow-sm">
        <router-link to="/">
            GitInteractions
        </router-link>
        <button
            class="navbar-toggler"
            type="button"
            data-toggle="collapse"
            data-target="#navbarSupportedContent"
            aria-controls="navbarSupportedContent"
            aria-expanded="false"
        >
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navbarSupportedContent">
            <form class="form-inline mx-auto">
                <div class="input-group input-group-lg" @click="search">
                    <div class="input-group-prepend">
                        <button class="btn btn-outline-secondary" type="button">
                            <i class="fa fa-search"></i>
                        </button>
                    </div>
                    <input
                        type="text"
                        class="form-control"
                        placeholder="Search..."
                    />
                </div>
            </form>

            <!-- Right Side Of Navbar -->
            <ul class="navbar-nav ml-auto">
                <div class="btn-group">
                    <button type="button" class="btn btn-link" @click="logout">
                        Logout
                    </button>

                    <notifications></notifications>
                </div>
            </ul>
        </div>
    </nav>
</template>

<script>
export default {
    methods: {
        search() {
            $("#search").modal("show");
        },

        logout() {
            this.$store.dispatch("logout");
        }
    }
};
</script>
