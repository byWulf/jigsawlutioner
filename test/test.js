const fs = require('fs');
const Matcher = require(__dirname + '/../src/matcher');
const debug = require(__dirname + '/../src/debug');
const BorderFinder = require(__dirname + '/../src/borderFinder');
const SideFinder = require(__dirname + '/../src/sideFinder');
const sharp = require('sharp');

/**
 * @return {Promise<object[]>}
 */
function getPieces() {
    return new Promise((resolve, reject) => {
        if (this.piecesLoaded) {
            resolve();
            return;
        }

        let pieces = [];

        const fs = require('fs');
        let dir = __dirname + '/../../jigsawlutioner-machine/projects/Default/pieces/';
        fs.readdir(dir, (err, fileNames) => {
            if (err) {
                reject(err);
                return;
            }

            fileNames.forEach((filename) => {
                let content = fs.readFileSync(dir + filename, 'utf-8');

                pieces.push(JSON.parse(content));
            });

            resolve(pieces);
        });
    });
}

(async() => {
    try {
        let pieces = await getPieces();

        console.log(pieces);
        console.log("start: ", Date.now());
        let placements = await Matcher.getPlacements(pieces);
        //let placements = JSON.parse(fs.readFileSync(__dirname + '/../../jigsawlutioner-machine/projects/Default/placements', 'utf-8'));
        console.log("end: ", Date.now());
        //debug.outputPlacements(placements);
        debug.createPlacementsImage(placements, 'foobar.png', {imagesPath: __dirname + '/../../jigsawlutioner-machine/projects/Default/images', threshold: 245, pieceSize: 256});
    } catch (e) {
        console.log(e);
    }
})();

/*
let photobox = require('../../jigsawlutioner-machine/src/stations/photobox');

(async() => {
    try {
        if (!photobox.piecesLoaded) {
            await photobox.loadPieces();
        }

        let piece71 = await photobox.getPieceFromFile(__dirname + '/../../jigsawlutioner-machine/images/piece70.jpg');
        let piece116 = await photobox.getPieceFromFile(__dirname + '/../../jigsawlutioner-machine/images/piece77.jpg');

        console.log('71 online', await photobox.api.call('findexistingpieceindex', {pieces: photobox.getApiPiecesList(photobox.pieces), piece: photobox.getApiPiece(piece71)}));

        console.log('116 online', await photobox.api.call('findexistingpieceindex', {pieces: photobox.getApiPiecesList(photobox.pieces), piece: photobox.getApiPiece(piece116)}));
    } catch (e) {
        console.log(e);
    }
})();*/
