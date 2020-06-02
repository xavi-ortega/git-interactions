import HomePage from "./pages/HomePage";
import ReportPage from "./pages/ReportPage";

export const routes = [
    { name: "Home", path: "/", component: HomePage },
    { name: "Report", path: "/report", component: ReportPage },
    {
        path: "*",
        component: {
            template: "<p>Not found</p>"
        }
    }
];
