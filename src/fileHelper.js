const fs = require('fs');

function isFilenameTaken(filename) {
    return new Promise((resolve) => {
        fs.stat(filename, (err, stats) => {
            resolve(!!stats);
        });
    });
}

function checkForFreeFilename(filename, resolve, reject) {
    isFilenameTaken(filename).then((exists) => {
        if (!exists) {
            resolve(filename);
            return;
        }

        let match = filename.match(/^(.*?)?(-([0-9]+))?(\.[a-zA-Z]+)?$/);
        let nextNumber = typeof match[3] !== 'undefined' ? parseInt(match[3], 10) + 1 : 0;

        checkForFreeFilename(match[1] + '-' + nextNumber + '' + match[4], resolve, reject);
    });
}

function getFreeFilename(filename) {
    return new Promise((resolve, reject) => {
        checkForFreeFilename(filename, resolve, reject);
    });
}

module.exports = {
    getFreeFilename: getFreeFilename
};