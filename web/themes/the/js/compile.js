const fs = require("fs");
const stylus = require("stylus");
const path = require("path");
const autoprefixer = require("autoprefixer-stylus");
// const poststylus = require("poststylus");

const log = console.log.bind(console);

function getFileList(dirPath, arrayOfFiles) {
  const files = fs.readdirSync(dirPath);
  arrayOfFiles = arrayOfFiles || [];
  files.forEach((file) => {
    const subDirPath = `${dirPath}/${file}`;
    if (fs.statSync(subDirPath).isDirectory()) {
      arrayOfFiles = getFileList(subDirPath, arrayOfFiles);
    } else {
      const arr = file.split(".");
      if (arr[1] === "styl" || arr[1] === "css") {
        //@TODO - make this an option.
        arrayOfFiles.push(path.join(dirPath, "/", file));
        // arrayOfFiles.push(path.join(__dirname, dirPath, "/", file))
      }
    }
  });
  return arrayOfFiles;
}

function writeCssFile(pathToFile, cssData, verbose = false) {
  fs.writeFile(pathToFile, cssData, (err) => {
    if (err) log(err);
    else {
      if (verbose) {
        log(pathToFile + " File written successfully\n");
      }
    }
  });
}

function prepareStyleData(pathToFile, destinationPath) {
  try {
    const data = fs.readFileSync(pathToFile, "utf8");
    const arr = destinationPath.split("/");
    const cssFilename = arr.pop().replace("styl", "css");
    const filePath = arr.join("/");
    const cssFullFilePath = path.join(filePath, cssFilename);
    fs.promises.mkdir(filePath, { recursive: true }).catch(console.error);
    return [data, cssFullFilePath];
  } catch (err) {
    throw err;
  }
}

const renderStylusCSS = (pathToStyle, destinationPath) => {
  const [data, cssFullFilePath] = prepareStyleData(
    pathToStyle,
    destinationPath
  );
  // renderCss(data, cssFullFilePath);
  stylus(data)
    .set("paths", ["stylus"])
    // .use('node_modules/poststylus/index.js')
    // .set('--line-numbers', true)
    // .include('./node_modules/postcss')
    // .use(poststylus([
    //   'autoprefixer',
    //   'cssnano',
    // ]))
    .render((err, css) => {
      if (err) throw err;
      writeCssFile(cssFullFilePath, css);
    });
};

function compileProject({ inputDir, outputDir }) {
  try {
    const arrayOfFiles = getFileList(inputDir, []);
    arrayOfFiles.forEach((pathToFile, i) => {
      const destinationPath = pathToFile.replace(inputDir, outputDir);
      renderStylusCSS(pathToFile, destinationPath);
    });
  } catch (e) {
    console.log(e);
  }
}

module.exports = { compileProject, renderStylusCSS };
