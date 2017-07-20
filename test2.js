const fs = require('fs');
const OpencvHelper = require('./src/opencvHelper');
const Jigsawlutioner = require('./src/jigsawlutioner');

let path = 'C:\\Users\\michael_wolf\\Pictures\\puzzle\\test_free';
fs.readdir(path, (err, files) => {
  files.forEach(file => {
    OpencvHelper.prepareImage(path + '\\' + file).then((newFilename) => {
        Jigsawlutioner.analyzeFile(newFilename).then((piece) => {

        });
    });
  });
});