import Vue from "vue";
import VueRouter from "vue-router";
import NProgress from "nprogress";

import HomePage from "./pages/HomePage";

Vue.use(VueRouter);

const routes = [
    {
        name: "Home",
        path: "/",
        meta: {
            auth: true
        },
        component: HomePage
    },
    {
        name: "Login",
        path: "/login",
        component: () => import("./pages/auth/LoginPage")
    },
    {
        name: "Register",
        path: "/register",
        component: () => import("./pages/auth/RegisterPage")
    },
    {
        name: "Report",
        path: "/report/:owner/:name/:id",
        meta: {
            auth: true
        },
        component: () => import("./pages/ReportPage")
    },
    {
        path: "*",
        component: {
            template: "<p>Not found</p>"
        }
    }
];

const router = new VueRouter({
    mode: "history",
    routes
});

NProgress.start();

router.beforeResolve((to, from, next) => {
    const loggedIn = localStorage.getItem("user");

    if (to.matched.some(record => record.meta.auth) && !loggedIn) {
        NProgress.start();
        next("/login");
        NProgress.done();
        return;
    }

    if (to.name) {
        NProgress.start();
    }
    next();
});

router.afterEach(() => NProgress.done());

export default router;
