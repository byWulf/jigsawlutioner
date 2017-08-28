const fs = require('fs');
const sharp = require('sharp');
const assert = require('chai').assert;

const BorderFinder = require('./src/borderFinder');
const Jigsawlutioner = require('./src/jigsawlutioner');

let width = 6;
let height = 3;
let multiplicator = 3;
let startNumber = 4287;

let targetMatchings = {};
let files = [];
for (let x = 0; x < width; x++) {
    for (let y = 0; y < height; y++) {
        for (let i = 0; i < multiplicator; i++) {
            let filename = 'IMG_' + ((y * width + x) * multiplicator + i + startNumber) + '.JPG';
            targetMatchings[filename] = [];
            files.push(filename);

            [[-1,0], [1,0], [0,-1], [0,1]].forEach(function(offsets) {
                if (x + offsets[0] >= 0 && x + offsets[0] < width && y + offsets[1] >= 0 && y + offsets[1] < height) {
                    for (let j = 0; j < 3; j++) {
                        let matchingFilename = 'IMG_' + (((y + offsets[1]) * width + x + offsets[0]) * multiplicator + j + startNumber) + '.JPG';

                        targetMatchings[filename].push(matchingFilename);
                    }
                }
            });
        }
    }
}

let pieces = {};
let done = 0;
files.forEach(file => {
    let image = sharp('C:\\Users\\michael_wolf\\Pictures\\puzzle\\test_neu2\\' + file);
    image.metadata().then((data) => {
        return image.extract({
            left: Math.round(data.width * 0.25),
            top: Math.round(data.height * 0.25),
            width: Math.round(data.width * 0.5),
            height: Math.round(data.height * 0.5)
        }).png().toFile('images\\' + file + '.resized.png');
    }).then(() => {
        return BorderFinder.findPieceBorder('images\\' + file + '.resized.png');
    }).then((border) => {
        return Jigsawlutioner.analyzeBorders(border.path);
    }).then((result) => {
        pieces[file] = result;

        done++;
        console.log(done + '/' + (width * height * multiplicator) + ' analysed');
        if (done === width * height * multiplicator) {
            let matchings = {};

            let checked = 0;
            for (let sourceFile in pieces) {
                if (!pieces.hasOwnProperty(sourceFile)) continue;
                matchings[sourceFile] = [];

                for (let sourceSideIndex = 0; sourceSideIndex < pieces[sourceFile].sides.length; sourceSideIndex++) {

                    for (let fileOffset = 0; fileOffset < multiplicator; fileOffset++) {
                        let bestResult = null;
                        let results = [];
                        for (let targetFileIndex = startNumber + fileOffset; targetFileIndex < startNumber + width * height * multiplicator; targetFileIndex += multiplicator) {
                            let targetFile = 'IMG_' + targetFileIndex + '.JPG';
                            if ('IMG_' + targetFileIndex + '.JPG' === sourceFile) continue;

                            for (let targetSideIndex = 0; targetSideIndex < pieces[targetFile].sides.length; targetSideIndex++) {
                                let match = Jigsawlutioner.getSideMatchingFactor(pieces[sourceFile].sides[sourceSideIndex], pieces[targetFile].sides[targetSideIndex], 0, 0);

                                results.push({
                                    filename: targetFile,
                                    match: match
                                });
                                if (match.matches && (bestResult === null || match.deviation < bestResult.match.deviation)) {
                                    bestResult = {
                                        filename: targetFile,
                                        match: match
                                    };
                                }
                            }
                        }
                        if (bestResult) {
                            for (let i = 0; i < results.length; i++) {
                                if (results[i].match.deviation <=  bestResult.match.deviation + 0.05) {
                                    matchings[sourceFile].push(results[i].filename);
                                }
                            }
                        }
                    }
                }

                try {
                    assert.isAtLeast(matchings[sourceFile].length, targetMatchings[sourceFile].length, sourceFile + ' has too less matches (expected matches with ' + targetMatchings[sourceFile].join('/') + ', actually matched with ' + matchings[sourceFile].join('/') + ')');
                    for (let i = 0; i < targetMatchings[sourceFile].length; i++) {
                        assert.include(matchings[sourceFile], targetMatchings[sourceFile][i], sourceFile + ' did not match with ' + targetMatchings[sourceFile][i] + ' (expected matches with ' + targetMatchings[sourceFile].join('/') + ', actually matched with ' + matchings[sourceFile].join('/') + ')');
                    }
                } catch (err) {
                    console.log(err.AssertionError);
                }

                checked++;
                console.log(checked + '/' + (width * height * multiplicator) + ' compared');
            }
        }
    }).catch((err) => {
        console.log(err);
    })
});
