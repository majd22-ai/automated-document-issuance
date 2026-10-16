import { defineConfig } from "vite";
import laravel from "laravel-vite-plugin";

export default defineConfig({
    plugins: [
        laravel({
            input: [
                "resources/css/design-system.css",
                "resources/css/public.css",
                "resources/css/citizen.css",
                "resources/js/app.js",
                "resources/js/homepage-fix.js",
            ],
            refresh: true,
        }),
    ],
});
