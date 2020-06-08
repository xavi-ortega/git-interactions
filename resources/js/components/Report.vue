<template>
    <div>
        <div class="row mb-4">
            <div class="col-4">
                <shell :title="report.repository.slug">
                    <p>{{ report.repository.description }}</p>
                </shell>
            </div>

            <div class="col-8">
                <shell title="Issues">
                    <div class="row mb-4">
                        <div class="col">
                            <radial-chart></radial-chart>
                        </div>

                        <div class="col">
                            <p>
                                <b>Closed in less than an hour:</b>
                                {{ report.issues.closed_less_than_one_hour }}
                            </p>
                            <p>
                                <b>Average time to close:</b>
                                {{ report.issues.avg_time_to_close }}
                            </p>
                        </div>
                    </div>
                </shell>
            </div>
        </div>

        <div class="row mb-4">
            <div class="col">
                <shell title="Pull Requests">
                    <div class="row mb-4">
                        <div class="col-6">
                            <bar-chart></bar-chart>
                        </div>

                        <div class="col-6">
                            <h3>Pull requests utility</h3>

                            <radial-chart></radial-chart>

                            <h3>Time statistics</h3>

                            <p>
                                <b>Closed in less than an hour:</b>
                                {{
                                    report.pullRequests
                                        .closed_less_than_one_hour
                                }}
                            </p>
                            <p>
                                <b>Merged in less than an hour:</b>
                                {{
                                    report.pullRequests
                                        .merged_less_than_one_hour
                                }}
                            </p>
                            <p>
                                <b>Average time to close:</b>
                                {{ report.pullRequests.avg_time_to_close }}
                            </p>
                            <p>
                                <b>Average time to merge:</b>
                                {{ report.pullRequests.avg_time_to_merge }}
                            </p>
                        </div>
                    </div>
                </shell>
            </div>
        </div>

        <div class="row mb-4">
            <div class="col-6">
                <shell title="Pull Requests Reviewers">
                    <radial-chart></radial-chart>
                </shell>
            </div>

            <div class="col-6">
                <shell title="Pull Requests Assignees">
                    <radial-chart></radial-chart>
                </shell>
            </div>
        </div>

        <div class="row mb-4">
            <div class="col-6">
                <div class="row mb-4">
                    <div class="col">
                        <shell title="Contributors">
                            <p>
                                <b>Total contributors:</b>
                                {{ report.contributors.total }}
                            </p>
                            <p>
                                <b>Average pull requests contributed:</b>
                                {{
                                    report.contributors
                                        .avg_pull_request_contributed
                                }}
                            </p>
                        </shell>
                    </div>
                </div>

                <div class="row mb-4">
                    <div class="col">
                        <shell title="Code">
                            <radial-chart></radial-chart>

                            <h3>Branches</h3>

                            <table class="table">
                                <thead>
                                    <tr>
                                        <th scope="col">Active</th>
                                        <th scope="col">Name</th>
                                        <th scope="col">Last activity</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="branch in report.code.branches"
                                        :key="branch.name"
                                    >
                                        <td>
                                            <input
                                                type="checkbox"
                                                :checked="branch.active"
                                                disabled
                                            />
                                        </td>
                                        <td>{{ branch.name }}</td>
                                        <td>
                                            {{ branch.lastActivity }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </shell>
                    </div>
                </div>
            </div>

            <div class="col-6">
                <shell title="Most changed files">
                    <div id="accordion">
                        <div
                            class="card"
                            v-for="file in report.code.top_changed_files"
                            :key="file.name"
                        >
                            <div class="card-header" id="headingThree">
                                <h5 class="mb-0">
                                    <button
                                        class="btn btn-link collapsed"
                                        data-toggle="collapse"
                                        @click="toggle"
                                    >
                                        {{ file.name }}
                                    </button>
                                </h5>
                            </div>
                            <div class="collapse" data-parent="#accordion">
                                <div class="card-body">
                                    <p>
                                        <b>Total changes:</b>
                                        {{ file.patches.length }}
                                    </p>

                                    <p>
                                        <b>Contributors:</b>
                                        {{ getFileContributors(file) }}
                                    </p>

                                    <template v-if="file.renames.length">
                                        <p><b>Renames</b></p>
                                        <ul class="list-group">
                                            <li
                                                class="list-group-item"
                                                v-for="rename of file.renames"
                                                :key="rename.newName"
                                            >
                                                {{ rename.new }}
                                                <small>{{ rename.date }}</small>
                                            </li>
                                        </ul>
                                    </template>
                                </div>
                            </div>
                        </div>
                    </div>
                </shell>
            </div>
        </div>
    </div>
</template>

<script>
export default {
    props: {
        report: {
            default: () => ({
                repository: {},
                issues: {},
                pullRequests: {},
                contributors: {},
                code: {}
            }),
            required: false
        }
    },

    methods: {
        toggle({ currentTarget }) {
            const target = $(currentTarget)
                .parent()
                .parent()
                .parent();

            const body = target.find(".collapse");

            if (body) {
                body.collapse("toggle");
            }
        },

        getFileContributors(file) {
            return file.patches
                .reduce((contributors, patch) => {
                    if (!contributors.includes(patch.owner.name)) {
                        contributors = [...contributors, patch.owner.name];
                    }

                    return contributors;
                }, [])
                .reduce(
                    (string, contributor) =>
                        `${string}${string.length ? "," : ""} ${contributor}`,
                    ""
                );
        }
    }
};
</script>
