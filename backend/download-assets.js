const fs = require("fs");
const path = require("path");
const https = require("https");

const publicDir = path.join(__dirname, "public");

const download = (url, dest) =>
    new Promise((resolve, reject) => {
        const file = fs.createWriteStream(dest);
        https
            .get(url, (response) => {
                if (
                    response.statusCode === 301 ||
                    response.statusCode === 302
                ) {
                    return download(response.headers.location, dest)
                        .then(resolve)
                        .catch(reject);
                }
                response.pipe(file);
                file.on("finish", () => {
                    file.close(resolve);
                });
            })
            .on("error", (err) => {
                fs.unlink(dest, () => {});
                reject(err);
            });
    });

async function downloadGoogleFonts(cssUrl, fontDirName, cssFileName) {
    const fontDir = path.join(publicDir, "fonts", fontDirName);
    fs.mkdirSync(fontDir, { recursive: true });

    return new Promise((resolve, reject) => {
        https
            .get(
                cssUrl,
                {
                    headers: {
                        "User-Agent":
                            "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/116.0.0.0 Safari/537.36",
                    },
                },
                (res) => {
                    let cssData = "";
                    res.on("data", (chunk) => (cssData += chunk));
                    res.on("end", async () => {
                        const urlRegex = /url\((https:\/\/[^)]+)\)/g;
                        let match;
                        const downloads = [];
                        let newCss = cssData;

                        while ((match = urlRegex.exec(cssData)) !== null) {
                            const fontUrl = match[1];
                            const fileName = path.basename(
                                new URL(fontUrl).pathname,
                            );
                            const localPath = `/fonts/${fontDirName}/${fileName}`;
                            const destPath = path.join(fontDir, fileName);

                            newCss = newCss.replace(fontUrl, localPath);
                            downloads.push(
                                download(fontUrl, destPath).then(() =>
                                    console.log(`Downloaded ${fileName}`),
                                ),
                            );
                        }

                        await Promise.all(downloads);
                        fs.writeFileSync(
                            path.join(fontDir, cssFileName),
                            newCss,
                        );
                        console.log(`Saved ${cssFileName}`);
                        resolve();
                    });
                },
            )
            .on("error", reject);
    });
}

async function downloadPackage(pkg, version, files, destDirName) {
    const destDir = path.join(publicDir, "assets", "vendor", destDirName);
    fs.mkdirSync(destDir, { recursive: true });

    for (const file of files) {
        const url = `https://cdn.jsdelivr.net/npm/${pkg}@${version}/${file}`;
        const localDest = path.join(destDir, path.basename(file));
        const dir = path.dirname(localDest);
        fs.mkdirSync(dir, { recursive: true });

        await download(url, localDest);
        console.log(`Downloaded ${localDest}`);
    }
}

async function main() {
    console.log("Downloading Google Fonts...");
    await downloadGoogleFonts(
        "https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Inter:wght@400;500;600;700&display=swap",
        "nextjs-fonts",
        "fonts.css",
    );
    await downloadGoogleFonts(
        "https://fonts.googleapis.com/css2?family=Kantumruy+Pro:ital,wght@0,100..700;1,100..700&family=Noto+Sans+Khmer:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Inter:wght@400;500;600;700&display=swap",
        "laravel-fonts",
        "fonts.css",
    );

    console.log("Downloading Remixicon...");
    await downloadPackage(
        "remixicon",
        "4.2.0",
        [
            "fonts/remixicon.css",
            "fonts/remixicon.woff2",
            "fonts/remixicon.woff",
            "fonts/remixicon.ttf",
        ],
        "remixicon",
    );

    console.log("Downloading Bootstrap Icons...");
    await downloadPackage(
        "bootstrap-icons",
        "1.11.3",
        [
            "font/bootstrap-icons.min.css",
            "font/fonts/bootstrap-icons.woff2",
            "font/fonts/bootstrap-icons.woff",
        ],
        "bootstrap-icons",
    );

    console.log("Downloading Summernote...");
    await downloadPackage(
        "summernote",
        "0.8.18",
        [
            "dist/summernote-lite.min.css",
            "dist/summernote-lite.min.js",
            "dist/font/summernote.woff2",
            "dist/font/summernote.woff",
            "dist/font/summernote.ttf",
        ],
        "summernote",
    );

    console.log("Done!");
}

main().catch(console.error);
