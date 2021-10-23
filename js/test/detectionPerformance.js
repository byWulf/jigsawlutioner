const fs = require('fs');

const sideFinder = require('../src/sideFinder');

let correct = 0;
let wrong = 0;
for (let i = 2; i <= 501; i++) {
    //if (i != 492) continue;
    let border = JSON.parse(fs.readFileSync(__dirname + '/fixtures/pieces/piece' + i + '_border.json'));
    let diffs = JSON.parse(fs.readFileSync(__dirname + '/fixtures/pieces/piece' + i + '_diffs.json'));
    let offsets = JSON.parse(fs.readFileSync(__dirname + '/fixtures/pieces/piece' + i + '_possibleCorners_corrected.json'));

    let gotOffsets = sideFinder.getPieceCornerOffsets(diffs);
    console.log(i);

    let isWrong = false;
    if (gotOffsets !== null) {
        for (let j = 0; j < gotOffsets.length; j++) {
            let found = false;
            for (let k = 0; k < offsets.length; k++) {
                if (offsets[k] == gotOffsets[j]) {
                    offsets.splice(k, 1);
                    found = true;
                    break;
                }
            }
            if (!found) {
                isWrong = true;
                break;
            }
        }
    }

    if (isWrong || offsets.length > 0) {
        wrong++;
    } else {
        correct++;
    }
}
console.log("Error rate: " + (Math.round((wrong / (correct + wrong)) * 100 * 10) / 10) + "%");