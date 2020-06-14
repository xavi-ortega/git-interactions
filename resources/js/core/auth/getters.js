export default {
    isLogged: state => !!state.auth,

    getUser: state => state.auth.user,

    getToken: state => state.auth.token
};
