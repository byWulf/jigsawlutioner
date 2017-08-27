const fs = require('fs');

const BorderFinder = require('./src/borderFinder');
const Jigsawlutioner = require('./src/jigsawlutioner');

/*
let pieceFolder = 'C:\\Users\\michael_wolf\\Pictures\\puzzle\\test_neu';
fs.readdir(pieceFolder, (err, files) => {
    files.forEach(file => {
        BorderFinder.findPieceBorder(pieceFolder + '\\' + file).then((border) => {
            Jigsawlutioner.analyzeBorders(border).then((result) => {
                fs.writeFile('./images/' + file + '.parsed.txt', JSON.stringify(result));
            });
        }).catch((err) => {
            console.log(err);
        })
    });
});*/

let filename = 'IMG_4284.JPG';

BorderFinder.findPieceBorder('C:\\Users\\michael_wolf\\Pictures\\puzzle\\test_neu\\' + filename).then((border) => {
    Jigsawlutioner.analyzeBorders(border).then((result) => {
        fs.writeFile('./images/' + filename + '.parsed.txt', JSON.stringify(result));
    }).catch((err) => {
        console.log(err);
    });
}).catch((err) => {
    console.log(err);
});