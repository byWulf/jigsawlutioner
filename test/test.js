const fs = require('fs');
const Matcher = require(__dirname + '/../src/matcher');
const debug = require(__dirname + '/../src/debug');
const BorderFinder = require(__dirname + '/../src/borderFinder');
const SideFinder = require(__dirname + '/../src/sideFinder');
const sharp = require('sharp');
const opn = require('opn');

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
        let dir = __dirname + '/fixtures/pieces/';
        fs.readdir(dir, (err, fileNames) => {
            if (err) {
                reject(err);
                return;
            }

            fileNames.forEach(async (filename) => {
                if (['piece2.jpg', 'piece3.jpg', 'piece4.jpg', 'piece27.jpg', 'piece28.jpg', 'piece29.jpg', 'piece52.jpg', 'piece53.jpg', 'piece54.jpg'].indexOf(filename) === -1) {
                    return;
                }

                //let content = fs.readFileSync(dir + filename, 'utf-8');

                console.log('Loading ' + dir + filename);

                let piece, sidePiece;

                try {
                    piece = await BorderFinder.findPieceBorder(dir + filename, {
                        threshold: 245,
                        reduction: 2,
                        debug: true
                    });
                } catch (err) {
                    console.log(err);
                }

                try {
                    sidePiece = await SideFinder.findSides(pieces.length, piece.path, null, {
                        debug: true,
                        filename: dir + filename
                    });
                    piece.pieceIndex = pieces.length;
                    piece.sides = sidePiece.sides;
                    piece.diffs = sidePiece.diffs;
                } catch (err) {
                    console.log(err);
                }

                fs.writeFileSync(dir + filename + '.parsed.json', JSON.stringify(piece));

                console.log(dir + filename + ' loaded');
                pieces.push(piece);

                if (pieces.length === 9) {
                    resolve(pieces);
                }
            });
        });
    });
}

(async() => {
    try {
        let regenerate = false;

        let placements;
        if (regenerate) {
            let pieces = await getPieces();

            console.log("pieces loaded: ", pieces.length);
            console.log("start placement-generation: ", Date.now());
            placements = await Matcher.getPlacements(pieces);
            fs.writeFileSync(__dirname + '/fixtures/placements', JSON.stringify(placements));
        } else {
            placements = JSON.parse(fs.readFileSync(__dirname + '/fixtures/placements', 'utf-8'));
        }
        console.log("end placement-generation:  ", Date.now());
        debug.outputPlacements(placements);
        await debug.createPlacementsImage(placements, __dirname + '/fixtures/placements.png', {imagesPath: __dirname + '/fixtures/pieces', pieceSize: 256});

        opn('file://' + __dirname + '/fixtures/placements.png').then(() => {
            console.log("closed?");
        });

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
