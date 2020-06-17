/**
 * First we will load all of this project's JavaScript dependencies which
 * includes Vue and other libraries. It is a great starting point when
 * building robust, powerful web applications using Vue and Laravel.
 */

require("./bootstrap");

window.Vue = require("vue");

import Echo from "laravel-echo";
import router from "./router";
import store from "./core";
import { mapGetters } from "vuex";
import { AuthService } from "./services/auth-service";

/**
 * The following block of code may be used to automatically register your
 * Vue components. It will recursively scan this directory for the Vue
 * components and automatically register them with their "basename".
 *
 * Eg. ./components/ExampleComponent.vue -> <example-component></example-component>
 */

const files = require.context("./", true, /\.vue$/i);
files.keys().map(key =>
    Vue.component(
        key
            .split("/")
            .pop()
            .split(".")[0],
        files(key).default
    )
);

/**
 * Next, we will create a fresh Vue application instance and attach it to
 * the page. Then, you may begin adding components to this application
 * or customize the JavaScript scaffolding to fit your unique needs.
 */

const app = new Vue({
    el: "#app",
    router,
    store,
    created() {
        const userData = AuthService.check();

        if (userData) {
            this.$store.commit("setUserData", userData);
        }

        axios.interceptors.response.use(
            response => response,
            error => {
                if (error.response.status === 401) {
                    this.$store.dispatch("logout");
                }
                return Promise.reject(error);
            }
        );

        window.Echo = new Echo({
            broadcaster: "pusher",
            key: process.env.MIX_PUSHER_APP_KEY,
            cluster: process.env.MIX_PUSHER_APP_CLUSTER,
            encrypted: true,
            wsHost: window.location.hostname,
            wsPort: 6001,
            disableStats: true,
            auth: {
                headers: {
                    Authorization: `Bearer ${userData.token}`
                }
            }
        });
    },
    computed: {
        ...mapGetters(["isLogged"])
    },

    methods: {
        search() {
            $("#search").modal("show");
        },

        logout() {
            this.$store.dispatch("logout");
        }
    }
});
