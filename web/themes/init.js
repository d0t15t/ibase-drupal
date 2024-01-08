const fs = require('fs')
const stylus = require('stylus')
const path = require('path');
const autoprefixer = require('autoprefixer-stylus')

const args = process.argv.slice(2);

const styleInput = path.join(args[0])
const styleOutput = path.join(args[1])

function getAllFiles (dirPath, arrayOfFiles) {
  const files = fs.readdirSync(dirPath)
  arrayOfFiles = arrayOfFiles || []
  files.forEach((file) => {
    if (fs.statSync(dirPath + "/" + file).isDirectory()) {
      arrayOfFiles = getAllFiles(dirPath + "/" + file, arrayOfFiles)
    } else {
      const arr = file.split('.');
      if (arr[1] === 'styl' || arr[1] === 'css') {
        arrayOfFiles.push(path.join(dirPath, "/", file))
        // arrayOfFiles.push(path.join(__dirname, dirPath, "/", file))
      }
    }
  })
  return arrayOfFiles
}

try {
  const arrayOfFiles = getAllFiles(styleInput, []);
  arrayOfFiles.forEach((pathToFile, i) => {
    const data = fs.readFileSync(pathToFile, 'utf8');
    const destinationPath = pathToFile.replace(styleInput, styleOutput)
    const arr = destinationPath.split('/');
    const cssFilename = arr.pop().replace('styl', 'css');
    const filePath = arr.join('/');
    const cssFullFilePath = path.join(filePath, cssFilename);
    fs.promises.mkdir(filePath, { recursive: true }).catch(console.error);

    stylus(data)
      // .set('filename', cssFullFilePath)
      .use(autoprefixer({ overrideBrowserslist: ['ie 7', 'ie 8'] }))
      .render((err, css) => {
        if (err) throw err;
        fs.writeFile(cssFullFilePath, css, (err) => {
          if (err)
            console.log(err);
          else {
            console.log(cssFullFilePath + " File written successfully\n");
          }
        });
      });

  })
} catch(e) {
  console.log(e)
}
