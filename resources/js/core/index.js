import Vue from "vue";
import Vuex from "vuex";
import axios from "axios";

import auth from "./auth";
import authMutations from "./auth/mutations";
import authActions from "./auth/actions";
import authGetters from "./auth/getters";

import report from "./report";
import reportMutations from "./report/mutations";
import reportActions from "./report/actions";
import reportGetters from "./report/getters";

Vue.use(Vuex);

axios.defaults.baseURL = "http://localhost:8000/api";

export default new Vuex.Store({
    state: {
        ...auth,
        ...report
    },

    mutations: {
        ...authMutations,
        ...reportMutations
    },

    actions: {
        ...authActions,
        ...reportActions
    },

    getters: {
        ...authGetters,
        ...reportGetters
    }
});
