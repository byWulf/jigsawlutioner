const fs = require('fs');
const Matcher = require(__dirname + '/../src/matcher');

(async() => {
    let pieces = JSON.parse(fs.readFileSync(__dirname + '/../../jigsawlutioner-machine/getplacements.json'));
    console.log(pieces);
    let placements = await Matcher.getPlacements(pieces.pieces);
    console.log(placements);
    Matcher.outputPlacements(placements);
})();