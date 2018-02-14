const fs = require('fs');
const Matcher = require(__dirname + '/../src/matcher');
const BorderFinder = require(__dirname + '/../src/borderFinder');
const SideFinder = require(__dirname + '/../src/sideFinder');
const sharp = require('sharp');

/*
(async() => {
    let pieces = JSON.parse(fs.readFileSync(__dirname + '/../../jigsawlutioner-machine/getplacements.json'));
    console.log(pieces);
    let placements = await Matcher.getPlacements(pieces.pieces);
    console.log(placements);
    Matcher.outputPlacements(placements);
})();*/

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
})();