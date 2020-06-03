<template>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">Register</div>

                    <div class="card-body">
                        <form method="POST" action="{{ route('register') }}">
                            @csrf

                            <div class="form-group row">
                                <label
                                    for="name"
                                    class="col-md-4 col-form-label text-md-right"
                                    >Name</label
                                >

                                <div class="col-md-6">
                                    <input
                                        id="name"
                                        type="text"
                                        class="form-control"
                                        name="name"
                                        required
                                        autocomplete="name"
                                        autofocus
                                        v-model="name"
                                    />
                                </div>
                            </div>

                            <div class="form-group row">
                                <label
                                    for="email"
                                    class="col-md-4 col-form-label text-md-right"
                                    >Email</label
                                >

                                <div class="col-md-6">
                                    <input
                                        id="email"
                                        type="email"
                                        class="form-control"
                                        name="email"
                                        required
                                        autocomplete="email"
                                        v-model="email"
                                    />
                                </div>
                            </div>

                            <div class="form-group row">
                                <label
                                    for="password"
                                    class="col-md-4 col-form-label text-md-right"
                                    >Password</label
                                >

                                <div class="col-md-6">
                                    <input
                                        id="password"
                                        type="password"
                                        class="form-control"
                                        name="password"
                                        required
                                        autocomplete="new-password"
                                        v-model="password"
                                    />
                                </div>
                            </div>

                            <div class="form-group row">
                                <label
                                    for="password-confirm"
                                    class="col-md-4 col-form-label text-md-right"
                                    >Confirm Password</label
                                >

                                <div class="col-md-6">
                                    <input
                                        id="password-confirm"
                                        type="password"
                                        class="form-control"
                                        name="password_confirmation"
                                        required
                                        autocomplete="new-password"
                                        v-model="password_confirmation"
                                    />
                                </div>
                            </div>

                            <div class="form-group row mb-0">
                                <div class="col-md-6 offset-md-4">
                                    <button
                                        type="submit"
                                        class="btn btn-primary"
                                    >
                                        Register
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
export default {
    data() {
        return {
            name: "",
            email: "",
            password: "",
            passsword_confirmation: ""
        };
    },

    methods: {
        register() {
            this.$store
                .dispatch("register", {
                    name: this.name,
                    email: this.email,
                    password: this.password,
                    password_confirmation: this.passsword_confirmation
                })
                .then(() => {
                    this.$router.push({ name: "Home" });
                })
                .catch(err => {
                    console.log(err);
                });
        }
    }
};
</script>
