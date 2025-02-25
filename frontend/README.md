# FS Poster Front-end

Front-end has two modes:

1. Production mode
2. Development mode

### Production mode

In this mode FS Poster uses minified files from `frontend/build` folder. This mode is enabled by default. We usually
update these files in the new versions of the plugin. If you want to use the latest version of the files, you can enable
the development mode.

Run `npm run build` in the `frontend` folder to build the files.

### Development mode

In this mode FS Poster uses Vite to serve the files from the `frontend/src` folder. This mode is useful if you want
to make changes in the front-end files or work with latest version. To enable this mode, you need to follow these steps:

1. Install _Node.js_ and _npm_
2. Run `npm install` in the `frontend` folder
3. If you are using UNIX-like OS (_Linux, macOS_), install `mkcert` and run `certificates/create-cert.sh` script. If you
   are using Windows, download `mkcert` and run `bash certificates/create-cert.sh` in terminal. Please note that you
   need a tool like Git Bash to bash scripts.
4. Open `wp-config.php` file and add the following code:
   ```php
   const FS_POSTER_DEV = true;
   ```
5. Run `npm run dev` in the `frontend` folder

### Switching between modes

Just comment/uncomment the `FS_POSTER_DEV` constant in the `wp-config.php` file to switch between modes.