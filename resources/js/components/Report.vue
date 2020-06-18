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
              <radial-chart :labels="['Open', 'Closed']" :dataset="issuesBreakdown"></radial-chart>
            </div>

            <div class="col">
              <p>
                <b>Total:</b>
                {{ report.issues.total }}
              </p>
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
              <bar-chart
                :labels="['Total', 'Open', 'Closed', 'Merged']"
                :dataset="pullRequestsBreakdown"
              ></bar-chart>

              <p>
                <b>Total:</b>
                {{ report.pullRequests.total }}
              </p>

              <p>
                <b>Pull requests without commits:</b>
                {{ report.pullRequests.closed_without_commits }}
              </p>
            </div>

            <div class="col-6">
              <h3>Pull requests utility</h3>

              <radial-chart :labels="['Useful', 'Useless']" :dataset="pullRequestsUtility"></radial-chart>

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
          <radial-chart
            :labels="[
                            'Good reviewers',
                            'Bad reviewers',
                            'Unexpected reviewers'
                        ]"
            :dataset="reviewersBreakdown"
          ></radial-chart>
        </shell>
      </div>

      <div class="col-6">
        <shell title="Pull Requests Assignees">
          <radial-chart
            :labels="[
                            'Good contributors',
                            'Bad contributors',
                            'Unexpected contributors'
                        ]"
            :dataset="assigneesBreakdown"
          ></radial-chart>
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

              <h3 class="mt-4 mb-3">Commit statistics</h3>
              <div class="commit-stats">
                <img src="../../img/commits.png" alt="commits" />
                <i class="fa fa-long-arrow-right"></i>
                <div>
                  <span>
                    <i class="fa fa-code"></i>
                    {{
                    report.contributors
                    .avg_lines_per_commit
                    }}
                    lines / commit
                  </span>
                  <span>
                    <i class="fa fa-file"></i>
                    {{
                    report.contributors
                    .avg_files_per_commit
                    }}
                    files / commit
                  </span>
                </div>
                <i class="fa fa-long-arrow-right"></i>

                <span>
                  <i class="fa fa-line-chart"></i>
                  {{
                  report.contributors
                  .avg_lines_per_file_per_commit
                  }}
                  lines / file / commit
                </span>
              </div>
            </shell>
          </div>
        </div>

        <div class="row mb-4">
          <div class="col">
            <shell title="Code">
              <radial-chart
                :labels="[
                                    'New work',
                                    'Rewrite own work',
                                    'Rewrite others work'
                                ]"
                :dataset="codeBreakdown"
              ></radial-chart>

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
                  <tr v-for="branch in report.code.branches" :key="branch.name">
                    <td>
                      <input type="checkbox" :checked="branch.active" disabled />
                    </td>
                    <td>{{ branch.name }}</td>
                    <td>{{ branch.lastActivity }}</td>
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
            <div class="card" v-for="file in report.code.top_changed_files" :key="file.name">
              <div class="card-header" id="headingThree">
                <h5 class="mb-0">
                  <button
                    class="btn btn-link collapsed"
                    data-toggle="collapse"
                    @click="toggle"
                  >{{ file.name }}</button>
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
                    <p>
                      <b>Renames</b>
                    </p>
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
  },

  computed: {
    issuesBreakdown() {
      const { open, closed } = this.report.issues;

      return {
        data: [open, closed],
        backgroundColor: ["#d96316", "#d8b116"]
      };
    },

    pullRequestsBreakdown() {
      const { total, open, closed, merged } = this.report.pullRequests;

      return {
        data: [total, open, closed, merged],
        backgroundColor: ["#defafc", "#d96316", "#d8b116", "#9ed816"]
      };
    },

    pullRequestsUtility() {
      const { total, merged } = this.report.pullRequests;

      return {
        data: [merged, total - merged],
        backgroundColor: ["#defafc", "#d96316"]
      };
    },

    reviewersBreakdown() {
      const {
        avg_prc_good_reviewers,
        avg_prc_bad_reviewers,
        avg_prc_unexpected_reviewers
      } = this.report.contributors;

      return {
        data: [
          avg_prc_good_reviewers,
          avg_prc_bad_reviewers,
          avg_prc_unexpected_reviewers
        ],
        backgroundColor: ["#9ed816", "#fb5968", "#d8b116"]
      };
    },

    assigneesBreakdown() {
      const {
        avg_prc_good_assignees,
        avg_prc_bad_assignees,
        avg_prc_unexpected_contributors
      } = this.report.contributors;

      return {
        data: [
          avg_prc_good_assignees,
          avg_prc_bad_assignees,
          avg_prc_unexpected_contributors
        ],
        backgroundColor: ["#9ed816", "#fb5968", "#d8b116"]
      };
    },

    codeBreakdown() {
      const {
        prc_new_code,
        prc_rewrite_own_code,
        prc_rewrite_others_code
      } = this.report.code;

      return {
        data: [prc_new_code, prc_rewrite_own_code, prc_rewrite_others_code],

        backgroundColor: ["#9ed816", "#d8b116", "#fb5968"]
      };
    }
  }
};
</script>
