import react from "@vitejs/plugin-react"
import * as fs from "fs"
import * as path from "path"
import { defineConfig } from "vite"

const DOMAIN = "fs-poster.ddev.site"
const SSL = true

export default defineConfig(({ command, mode }) => {
    return {
        define: {
            __BUILD_DATE_ISO__: JSON.stringify(new Date().toISOString()),
        },
        plugins: [react()],
        resolve: {
            alias: {
                "@": path.resolve(__dirname, "./src/"),
            },
            extensions: [".js", ".ts", ".jsx", ".tsx", ".json"],
        },
        css: {
            modules: {
                scopeBehaviour: "local",
                localsConvention: "dashesOnly",
                generateScopedName:
                    mode === "production" ? "fs-poster-[hash:base64:7]" : "[name]__[local]__[hash:base64:7]",
            },
        },
        build: {
            manifest: "manifest.json",
            target: "es2015",
            outDir: "build",
            chunkSizeWarningLimit: 10240,
            assetsInlineLimit: 20480,
            emptyOutDir: true,
            cssCodeSplit: false,
            rollupOptions: {
                input: {
                    metabox: "./src/metabox.tsx",
                    dashboard: "./src/dashboard.tsx",
                    portal: "./src/portal.tsx",
                },
                output: {
                    entryFileNames: "fs-poster.[name].[hash].js",
                    assetFileNames: "fs-poster.[name].[hash].[ext]",
                    format: "es",
                },
            },
        },
        server: {
            host: DOMAIN,
            port: 3000,
            strictPort: true,
            https:
                mode === "production" || !SSL
                    ? undefined
                    : {
                          key: fs.readFileSync(`certificates/${DOMAIN}-key.pem`),
                          cert: fs.readFileSync(`certificates/${DOMAIN}.pem`),
                      },
            hmr: {
                protocol: SSL ? "wss" : "ws",
                host: DOMAIN,
            },
        },
        assetsInclude: ["**/*.svg"],
    }
})
