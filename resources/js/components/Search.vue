<template>
    <div id="search" class="modal" tabindex="-1" role="dialog">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Search repository</h5>
                    <button
                        type="button"
                        class="close"
                        data-dismiss="modal"
                        aria-label="Close"
                    >
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="input-group input-group-lg">
                        <div class="input-group-prepend">
                            <button
                                class="btn btn-outline-secondary"
                                type="button"
                                @click="search"
                            >
                                <i class="fa fa-search"></i>
                            </button>
                        </div>
                        <input
                            type="text"
                            class="form-control"
                            placeholder="Search..."
                            v-model="searchQuery"
                            autofocus
                        />
                    </div>

                    <div
                        class="list-group my-3"
                        v-if="searchResults.reports.length"
                    >
                        <a
                            href="#"
                            class="list-group-item"
                            @click="go(report)"
                            v-for="report in searchResults.reports"
                            :key="report.id"
                        >
                            {{ searchResults.repository.slug }}
                            <small>{{ report.since }}</small>
                        </a>
                    </div>

                    <div v-if="error" class="alert alert-warning my-3">
                        {{ error }}
                    </div>

                    <button
                        v-if="canCreate"
                        class="btn btn-block btn-secondary"
                        @click="create"
                    >
                        Create
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
export default {
    data() {
        return {
            searchQuery: "",
            searchResults: {
                repository: {},
                reports: []
            },
            error: "",
            canCreate: false
        };
    },

    methods: {
        go(report) {
            this.$router.push({
                name: "Report",
                params: {
                    id: report.id,
                    name: report.repository.name,
                    owner: report.repository.owner
                }
            });
            this.dismiss();
        },

        search() {
            const [owner, name] = this.searchQuery.split("/");

            axios
                .post("report/search", {
                    owner,
                    name
                })
                .then(({ data }) => {
                    this.searchResults = data;

                    if (!data.reports.length) {
                        this.error =
                            "No reports were found. But you can make one";
                    }

                    this.canCreate = true;
                    this.error = undefined;
                })
                .catch(error => {
                    console.error("report -> search", error.response);
                    if (error.response) {
                        switch (error.response.status) {
                            case 404:
                                this.reset();
                                this.error = "Repository not found";
                                break;

                            case 422:
                                this.reset();
                                this.error =
                                    "Invalid repository name. Example: owner/name";

                            default:
                                break;
                        }
                    }

                    this.canCreate = false;
                });
        },

        create() {
            const [owner, name] = this.searchQuery.split("/");

            axios
                .post("report/prepare", {
                    owner,
                    name
                })
                .then(({ data }) => {
                    this.$router.push({
                        name: "Report",
                        params: { owner, name, id: data.report.id }
                    });
                    this.dismiss();
                })
                .catch(error => {
                    console.error("report -> search", error.response);

                    this.reset();
                    this.error = "Something happend. Please try again";

                    this.canCreate = false;
                });
        },

        reset() {
            this.searchResults = {
                repository: {},
                reports: []
            };
        },

        dismiss() {
            $("#search").modal("hide");
        }
    }
};
</script>
