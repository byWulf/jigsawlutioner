const assert = require('assert');
const fs = require('fs');

const BorderFinder = require('../src/borderFinder');
const SideFinder = require('../src/sideFinder');

(async () => {
    console.time();
    let borderData = await BorderFinder.findPieceBorder(__dirname + '/../../tests/fixtures/pieces/piece2.jpg', {
        threshold: 245,
        reduction: 2,
        debug: true,
        returnColorPoints: true
    });
    console.timeEnd();
    return;


        let ok = 0;
        let error = 0;
        for (let i = 2; i <= 501; i++) {
            console.log(i - 2);
            try {
                let borderData = await BorderFinder.findPieceBorder(__dirname + '/fixtures/pieces/piece' + i + '.jpg', {
                    threshold: 245,
                    reduction: 2,
                    debug: true,
                    returnColorPoints: true
                });
                let sideData = await SideFinder.findSides(i - 2, borderData.path, borderData.colorPoints, {debug: true, filename: __dirname + '/fixtures/pieces/piece' + i + '.jpg'});

                fs.writeFileSync(__dirname + '/fixtures/pieces/piece' + i + '.json', JSON.stringify(sideData));

                ok++;
            } catch (e) {
                console.log(e);
                error++;
            }
            break;
        }
        console.log("Fertig. Ok: " + ok + ", Fail: " + error);
})();