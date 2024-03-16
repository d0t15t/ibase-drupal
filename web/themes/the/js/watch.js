const chokidar = require('chokidar');
const fs = require("fs");
const { exec } = require("child_process");
const { renderStylusCSS } = require('./compile');
const log = console.log.bind(console);

const args = process.argv.slice(2);
if (!args.length) return;

try {
  // pre-compile
  exec("yarn compile", (error, stdout, stderr) => {
    if (error) {
      console.log(`error: ${error.message}`);
      return;
    }
    if (stderr) {
      console.log(`stderr: ${stderr}`);
      return;
    }
    console.log(`stdout: ${stdout}`);
  });

  const directories = args.map((dir, i) => `./${dir}`)
    .filter((dir) => fs.existsSync(dir));

  log(`Begin watching: ${directories}`);

  chokidar.watch(directories, {
    ignored: /(^|[\/\\])\../, // ignore dotfiles
  }).on('change', pathToFile => {
    const destinationPath = pathToFile.replace('stylus', 'css');//@todo - add to options?
    renderStylusCSS(pathToFile, destinationPath)
    log(pathToFile, destinationPath)
  });

} catch (e) {
  console.error(e);
}
