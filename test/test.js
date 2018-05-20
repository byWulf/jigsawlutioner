const fs = require('fs');
const Matcher = require(__dirname + '/../src/matcher');
const debug = require(__dirname + '/../src/debug');
const BorderFinder = require(__dirname + '/../src/borderFinder');
const SideFinder = require(__dirname + '/../src/sideFinder');
const PieceHelper = require(__dirname + '/../src/pieceHelper');
const sharp = require('sharp');
const opn = require('opn');

/**
 * @return {Promise<object[]>}
 */
function getPieces(fromX, toX, fromY, toY) {
    return new Promise((resolve, reject) => {
        if (this.piecesLoaded) {
            resolve();
            return;
        }

        let dir = __dirname + '/fixtures/pieces/';
        let fileNames = [];
        for (let x = fromX; x <= toX; x++) {
            for (let y = fromY; y <= toY; y++) {
                fileNames.push('piece' + (2 + y*25 + x) + '.jpg');
            }
        }

        let pieces = [];
        fileNames.forEach(async (filename) => {
            console.log('Loading ' + dir + filename);

            let piece, sidePiece, limitedPiece;

            if (0 && fs.existsSync(dir + filename + '.parsed.json')) {
                piece = JSON.parse(fs.readFileSync(dir + filename + '.parsed.json', 'utf-8'));
            } else {

                try {
                    piece = await BorderFinder.findPieceBorder(dir + filename, {
                        threshold: 245,
                        reduction: 2,
                        returnTransparentImage: true
                    });
                } catch (err) {
                    console.log(err);
                }

                try {
                    sidePiece = await SideFinder.findSides(pieces.length, piece.path, null);
                } catch (err) {
                    console.log(err);
                }

                limitedPiece = PieceHelper.getLimitedPiece(piece, sidePiece);

                fs.writeFileSync(dir + filename + '.parsed.json', JSON.stringify(limitedPiece));
            }

            console.log(dir + filename + ' loaded');
            pieces.push(limitedPiece);

            if (pieces.length === fileNames.length) {
                resolve(pieces);
            }
        });
    });
}

function sleep(ms) {
    return new Promise((resolve) => {
        setTimeout(resolve, ms);
    });
}

(async() => {
    try {
        let regenerate = true;

        let placements;
        if (regenerate) {
            let pieces = await getPieces(0,3,0,3);

            console.log("pieces loaded: ", pieces.length);
            console.log("start placement-generation: ", Date.now());
            placements = await Matcher.getPlacements(pieces);
            fs.writeFileSync(__dirname + '/fixtures/placements', JSON.stringify(placements));
        } else {
            placements = JSON.parse(fs.readFileSync(__dirname + '/fixtures/placements', 'utf-8'));
        }
        console.log("end placement-generation:  ", Date.now());
        debug.outputPlacements(placements);
        await debug.createPlacementsImage(placements, __dirname + '/fixtures/placements.png', {imagesPath: __dirname + '/fixtures/pieces', pieceSize: 48});

        console.log("done");
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
