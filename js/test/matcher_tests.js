const Matcher = require('../src/matcher');
const BorderFinder = require('../src/borderFinder');
const SideFinder = require('../src/sideFinder');
const fs = require('fs');

/*BorderFinder.findPieceBorder(__dirname + '/fixtures/pieces/piece2.jpg', {
    debug: true,
    threshold: 245,
    reduction: 2,
    returnColorPoints: true
}).then((borders) => {
    SideFinder.findSides(i - 2)
});
*/

/*let placements = JSON.parse(fs.readFileSync(__dirname + '/fixtures/pieces/placements.json'));

for (let g = 0; g < placements.length; g++) {
    let groupPlacements = placements[g];

    let minX = 0, maxX = 0, minY = 0, maxY = 0;
    for (let x in groupPlacements) {
        if (!groupPlacements.hasOwnProperty(x)) continue;

        minX = Math.min(minX, x);
        maxX = Math.max(maxX, x);

        for (let y in groupPlacements[x]) {
            if (!groupPlacements[x].hasOwnProperty(y)) continue;

            minY = Math.min(minY, y);
            maxY = Math.max(maxY, y);
        }
    }

    let str1 = '';
    for (let y = minY; y <= maxY; y++) {
        str1 = '';
        let str2 = '';
        let str3 = '';
        let str32 = '';
        let str4 = '';
        for (let x = minX; x <= maxX; x++) {
            let pieceIndex = 0;
            let rotation = 0;
            let customX = 0;
            let customY = 0;
            if (typeof groupPlacements[x] !== 'undefined' && typeof groupPlacements[x][y] !== 'undefined') {
                pieceIndex = groupPlacements[x][y].pieceIndex;
                rotation = groupPlacements[x][y].rotation;
                customY = Math.floor((pieceIndex - 2) / 25) + 1;
                customX = (pieceIndex - 2) - ((customY - 1) * 25) + 1;

                customY = (Math.abs(customY) < 10 ? ' ' : '') + (customY >= 0 ? ' ' : '') + customY;
                customX = (Math.abs(customX) < 10 ? ' ' : '') + (customX >= 0 ? ' ' : '') + customX;
            }

            str1 += '+-----';
            str2 += '|' + (pieceIndex > 0 && rotation === 0 ? '*' : ' ') + '   ' + (pieceIndex > 0 && rotation === 3 ? '*' : ' ');
            str3 += '| ' + (pieceIndex > 0 ? customX : '   ') + ' ';
            str32 += '| ' + (pieceIndex > 0 ? customY : '   ') + ' ';
            str4 += '|' + (pieceIndex > 0 && rotation === 1 ? '*' : ' ') + '   ' + (pieceIndex > 0 && rotation === 2 ? '*' : ' ');
        }
        str1 += '+';
        str2 += '|';
        str3 += '|';
        str32 += '|';
        str4 += '|';

        console.log(str1);
        console.log(str2);
        console.log(str3);
        console.log(str32);
        console.log(str4);
    }
    console.log(str1);
}*/

/*
let pieces = [];
let fixedPieces = [];
for (let i = 2; i <= 501; i++) {
    if (fs.existsSync(__dirname + '/fixtures/pieces/piece' + i + '.json')) {
        let piece = JSON.parse(fs.readFileSync(__dirname + '/fixtures/pieces/piece' + i + '.json'));
        pieces.push(piece);
        fixedPieces.push(piece);
    } else {
        pieces.push(null);
    }
}

console.log(pieces.length + ' pieces loaded.');

let width = 25;
let height = 20;
let sideMapping = {
    0: {x: -1, y:  0, side: 2, key: 'left'},
    1: {x:  0, y:  1, side: 3, key: 'bottom'},
    2: {x:  1, y:  0, side: 0, key: 'right'},
    3: {x:  0, y: -1, side: 1, key: 'top'},
};

let result = {
    correctSingle: 0,
    correctMultiple: 0,
    failSingle: 0,
    failMultiple: 0,
    failNothing: 0
};
for (let y = 0; y < height; y++) {
    for (let x = 0; x < width; x++) {
        let piece = pieces[x + y * width];
        if (!piece) continue;

        for (let side = 0; side < 4; side++) {
            let matchingX = x + sideMapping[side].x;
            if (matchingX < 0 || matchingX >= width) continue;

            let matchingY = y + sideMapping[side].y;
            if (matchingY < 0 || matchingY >= height) continue;

            let matchingPiece = pieces[matchingX + matchingY * width];
            if (!matchingPiece) continue;

            let matchingSide = sideMapping[side].side;

            let matches = Matcher.findMatchingPieces(piece, fixedPieces, side)[side];
            let resultString = '';
            if (matches.length > 1) {
                let containing = false;
                for (let i = 0; i < matches.length; i++) {
                    if (matches[i].pieceIndex === matchingPiece.pieceIndex && matches[i].sideIndex === matchingSide) {
                        containing = true;
                        break;
                    }
                }

                if (containing) {
                    resultString = 'Korrekt, aber ' + (matches.length - 1) + ' andere ebenfalls passend';
                    result.correctMultiple++;
                } else {
                    resultString = 'Falsch (mehrere andere passend)';
                    result.failMultiple++;
                }
            } else if (matches.length === 1) {
                if (matches[0].pieceIndex === matchingPiece.pieceIndex && matches[0].sideIndex === matchingSide) {
                    resultString = 'Korrekt';
                    result.correctSingle++;
                } else {
                    resultString = 'Falsch (ein anderes passend)';
                    result.failSingle++;
                }
            } else {
                resultString = 'Falsch (gar kein passendes)';
                result.failNothing++;
            }

            console.log("Original Side " + (x + 1) + "/" + (y + 1) + "/" + sideMapping[side].key + " (piece " + piece.pieceIndex + ") <-> Matching Side " + (matchingX + 1) + "/" + (matchingY + 1) + "/" + sideMapping[matchingSide].key + " (piece " + matchingPiece.pieceIndex + ") => " + resultString);

        }
    }
}
console.log(result);*/
/*

let placements = Matcher.getPlacements(pieces);
fs.writeFileSync(__dirname + '/fixtures/pieces/placements.json', JSON.stringify(placements));

console.log(placements);*/

/*const synaptic = require('synaptic');
const Canvas = require('canvas');
//let network = new synaptic.Architect.Perceptron(500, 700, 1);

let pieces = [];
for (let i = 2; i <= 501; i++) {
    if ([22,47,49,73,80,82,125,141,156,181,189,210,223,232,246,326,348,353,375,392,397,461,465].indexOf(i) === -1 && fs.existsSync(__dirname + '/fixtures/pieces/piece' + i + '.json')) {
        let piece = JSON.parse(fs.readFileSync(__dirname + '/fixtures/pieces/piece' + i + '_small.json'));
        pieces.push(piece);
        
        const canvas = new Canvas(500,1000);
        const context = canvas.getContext('2d');

        for (let a = 0; a < piece.sides.length; a++) {
            let side = piece.sides[a];
            for (let i = 0; i < side.points.length; i++) {
                let radius = 6;
                context.beginPath();
                context.arc(side.points[i].x + 200, side.points[i].y + 100 + a * 250, radius, 0, 2 * Math.PI, false);
                context.fillStyle = 'rgb(' + side.points[i].color[0] + ', ' + side.points[i].color[1] + ', ' + side.points[i].color[2] + ')';
                context.fill();
            }
        }

        let out = fs.createWriteStream(__dirname + '/fixtures/pieces/piece' + i + '_sides.png');
        let stream = canvas.pngStream();

        stream.on('data', function(chunk){
          out.write(chunk);
        });
        
    } else {
        pieces.push(null);
    }
}
fs.writeFileSync(__dirname + '/fixtures/pieces/allPiecesSmall.json', JSON.stringify(pieces));

console.log(pieces.length + ' pieces loaded.');*/
/*
let width = 25;
let height = 20;
let sideMapping = {
    0: {x: -1, y:  0, side: 2, key: 'left'},
    1: {x:  0, y:  1, side: 3, key: 'bottom'},
    2: {x:  1, y:  0, side: 0, key: 'right'},
    3: {x:  0, y: -1, side: 1, key: 'top'},
};

let limit = 5;
let trainingSets = [];
let trainingSetCorrects = 0;
let testSet = {};

let statistics = {
    correct: [],
    wrong: []
};
for (let i = 0; i < 50; i++) {
    statistics.correct.push({
        x: {min: 10000000, max: -10000000, sum: 0, count: 0},
        y: {min: 10000000, max: -10000000, sum: 0, count: 0},
        r: {min: 10000000, max: -10000000, sum: 0, count: 0},
        g: {min: 10000000, max: -10000000, sum: 0, count: 0},
        b: {min: 10000000, max: -10000000, sum: 0, count: 0},
    });
    statistics.wrong.push({
        x: {min: 10000000, max: -10000000, sum: 0, count: 0},
        y: {min: 10000000, max: -10000000, sum: 0, count: 0},
        r: {min: 10000000, max: -10000000, sum: 0, count: 0},
        g: {min: 10000000, max: -10000000, sum: 0, count: 0},
        b: {min: 10000000, max: -10000000, sum: 0, count: 0},
    });
}
for (let y = 0; y < height; y++) {
    for (let x = 0; x < width; x++) {
        let piece = pieces[x + y * width];
        if (!piece) continue;
        if (x >= limit || y >= limit) continue; //Save them to test it afterwards

        for (let side = 0; side < 4; side++) {
            if (piece.sides[side].direction === 'straight') continue;
            if (piece.sides[side].direction === 'out') continue; //nur "in" als quelle

            let matchingX = x + sideMapping[side].x;
            if (matchingX < 0 || matchingX >= width) continue;

            let matchingY = y + sideMapping[side].y;
            if (matchingY < 0 || matchingY >= height) continue;

            let matchingPiece = pieces[matchingX + matchingY * width];
            if (!matchingPiece) continue;

            let matchingSide = sideMapping[side].side;

            for (let compareY = 0; compareY < height; compareY++) {
                for (let compareX = 0; compareX < width; compareX++) {
                    let comparePiece = pieces[compareX + compareY * width];
                    if (!comparePiece) continue;
                    //if (compareX >= limit || compareY >= limit) continue; //Save them to test it afterwards

                    for (let compareSide = 0; compareSide < 4; compareSide++) {
                        if (comparePiece.sides[compareSide].direction === 'straight') continue;
                        if (comparePiece.sides[compareSide].direction === 'in') continue; //nur "out" als comparision

                        let set = {
                            input: [],
                            output: []
                        };*/
                        /*for (let i = 0; i < piece.sides[side].points.length; i++) {
                            set.input.push(Math.round(piece.sides[side].points[i].x * 1000) / 1000);
                            set.input.push(Math.round(piece.sides[side].points[i].y * 1000) / 1000);
                            set.input.push(piece.sides[side].points[i].color[0]);
                            set.input.push(piece.sides[side].points[i].color[1]);
                            set.input.push(piece.sides[side].points[i].color[2]);
                        }
                        for (let i = comparePiece.sides[compareSide].points.length - 1; i >= 0; i--) {
                            set.input.push(Math.round(-comparePiece.sides[compareSide].points[i].x * 1000) / 1000);
                            set.input.push(Math.round(-comparePiece.sides[compareSide].points[i].y * 1000) / 1000);
                            set.input.push(comparePiece.sides[compareSide].points[i].color[0]);
                            set.input.push(comparePiece.sides[compareSide].points[i].color[1]);
                            set.input.push(comparePiece.sides[compareSide].points[i].color[2]);
                        }*/
/*
                        let isCorrect = comparePiece.pieceIndex === matchingPiece.pieceIndex && compareSide === matchingSide ? 1 : 0;

                        for (let i = 0; i < 50; i++) {
                            let statItem = statistics[isCorrect?'correct':'wrong'][i];

                            let valueX = Math.round(piece.sides[side].points[i].x * 1000) / 1000 - Math.round(-comparePiece.sides[compareSide].points[49 - i].x * 1000) / 1000;
                            statItem.x.min = Math.min(statItem.x.min, Math.abs(valueX));
                            statItem.x.max = Math.max(statItem.x.max, Math.abs(valueX));
                            statItem.x.sum += Math.abs(valueX);
                            statItem.x.count++;


                            let valueY = Math.round(piece.sides[side].points[i].y * 1000) / 1000 - Math.round(-comparePiece.sides[compareSide].points[49 - i].y * 1000) / 1000;
                            statItem.y.min = Math.min(statItem.y.min, Math.abs(valueY));
                            statItem.y.max = Math.max(statItem.y.max, Math.abs(valueY));
                            statItem.y.sum += Math.abs(valueY);
                            statItem.y.count++;


                            let valueR = piece.sides[side].points[i].color[0] - comparePiece.sides[compareSide].points[49 - i].color[0];
                            statItem.r.min = Math.min(statItem.r.min, Math.abs(valueR));
                            statItem.r.max = Math.max(statItem.r.max, Math.abs(valueR));
                            statItem.r.sum += Math.abs(valueR);
                            statItem.r.count++;


                            let valueG = piece.sides[side].points[i].color[1] - comparePiece.sides[compareSide].points[49 - i].color[1];
                            statItem.g.min = Math.min(statItem.g.min, Math.abs(valueG));
                            statItem.g.max = Math.max(statItem.g.max, Math.abs(valueG));
                            statItem.g.sum += Math.abs(valueG);
                            statItem.g.count++;


                            let valueB = piece.sides[side].points[i].color[2] - comparePiece.sides[compareSide].points[49 - i].color[2];
                            statItem.b.min = Math.min(statItem.b.min, Math.abs(valueB));
                            statItem.b.max = Math.max(statItem.b.max, Math.abs(valueB));
                            statItem.b.sum += Math.abs(valueB);
                            statItem.b.count++;

                            set.input.push(Math.round(valueX * 1000) / 1000);
                            set.input.push(Math.round(valueY * 1000) / 1000);
                            set.input.push(valueR);
                            set.input.push(valueG);
                            set.input.push(valueB);
                        }

                        set.output.push(isCorrect ? 0 : 1);
                        set.output.push(isCorrect ? 1 : 0);

                        if (set.output[1] || Math.random() < 0.05) trainingSets.push(set);
                        if (set.output[1]) trainingSetCorrects++;

                        /*let values = [];

                        for (let i = 0; i < 50; i++) {
                            values.push(Math.round((piece.sides[side].points[i].x - -comparePiece.sides[compareSide].points[49 - i].x) * 1000) / 1000);
                            values.push(Math.round((piece.sides[side].points[i].y - -comparePiece.sides[compareSide].points[49 - i].y) * 1000) / 1000);
                            values.push(piece.sides[side].points[i].color[0] - comparePiece.sides[compareSide].points[49 - i].color[0]);
                            values.push(piece.sides[side].points[i].color[1] - comparePiece.sides[compareSide].points[49 - i].color[1]);
                            values.push(piece.sides[side].points[i].color[2] - comparePiece.sides[compareSide].points[49 - i].color[2]);
                        }

                        values.push(comparePiece.pieceIndex === matchingPiece.pieceIndex && compareSide === matchingSide ? 1 : 0);
                        fs.appendFileSync(__dirname + '/fixtures/dataset_full.csv', values.join(',') + '\n');*/
/*
                    }
                }
            }
        }
        console.log(x, y, trainingSets.length);
    }
}

fs.writeFileSync(__dirname + '/fixtures/dataSets/matchingFullWithColors.json', JSON.stringify(trainingSets));
*/
/*for (let i = 0; i < 50; i++) {
    statistics.correct[i].x.avg = statistics.correct[i].x.sum / statistics.correct[i].x.count;
    statistics.correct[i].y.avg = statistics.correct[i].y.sum / statistics.correct[i].y.count;
    statistics.correct[i].r.avg = statistics.correct[i].r.sum / statistics.correct[i].r.count;
    statistics.correct[i].g.avg = statistics.correct[i].g.sum / statistics.correct[i].g.count;
    statistics.correct[i].b.avg = statistics.correct[i].b.sum / statistics.correct[i].b.count;
    delete statistics.correct[i].x.sum;
    delete statistics.correct[i].y.sum;
    delete statistics.correct[i].r.sum;
    delete statistics.correct[i].g.sum;
    delete statistics.correct[i].b.sum;
    delete statistics.correct[i].x.count;
    delete statistics.correct[i].y.count;
    delete statistics.correct[i].r.count;
    delete statistics.correct[i].g.count;
    delete statistics.correct[i].b.count;
    statistics.wrong[i].x.avg = statistics.wrong[i].x.sum / statistics.wrong[i].x.count;
    statistics.wrong[i].y.avg = statistics.wrong[i].y.sum / statistics.wrong[i].y.count;
    statistics.wrong[i].r.avg = statistics.wrong[i].r.sum / statistics.wrong[i].r.count;
    statistics.wrong[i].g.avg = statistics.wrong[i].g.sum / statistics.wrong[i].g.count;
    statistics.wrong[i].b.avg = statistics.wrong[i].b.sum / statistics.wrong[i].b.count;
    delete statistics.wrong[i].x.sum;
    delete statistics.wrong[i].y.sum;
    delete statistics.wrong[i].r.sum;
    delete statistics.wrong[i].g.sum;
    delete statistics.wrong[i].b.sum;
    delete statistics.wrong[i].x.count;
    delete statistics.wrong[i].y.count;
    delete statistics.wrong[i].r.count;
    delete statistics.wrong[i].g.count;
    delete statistics.wrong[i].b.count;
}

console.log(JSON.stringify(statistics));
fs.writeFileSync(__dirname + '/fixtures/stats.json', JSON.stringify(statistics));*/
/*
let network = new synaptic.Architect.Perceptron(100, 50, 50, 2);
let trainer = new synaptic.Trainer(network);

console.log("training started");
trainer.train(trainingSets, {
    rate: .1,
    iterations: 10000,
    error: 0.00000000005,
    shuffle: true,
    log: 1
});*/

/*
for (let a = 0; a < 10000; a++) {
    let set = trainingSets[Math.floor(Math.random() * trainingSets.length)];

    network.activate(set.input);
    network.propagate(0.1, set.output);
    console.log(a);
}
*/
/*
console.log("checking");

let pieceIndexResults = {};
for (let key in testSet) {
    if (!testSet.hasOwnProperty(key)) continue;
    for (let i = 0; i < testSet[key].length; i++) {
        if (typeof pieceIndexResults[key] === 'undefined') pieceIndexResults[key] = [];
        result = network.activate(testSet[key][i].input);
        pieceIndexResults[key].push({expected: testSet[key][i].output, got: result, correct: (result[1] > result[0] ? testSet[key][i].output[1] === 1 : testSet[key][i].output[0] === 1)})
    }
}*/
/*
let pieceIndexResults = {};
for (let i = 0; i < testSet.length; i++) {
    let key = testSet[i].pieceIndex + '_' + testSet[i].side;
    if (typeof pieceIndexResults[key] === 'undefined') pieceIndexResults[key] = [];
    pieceIndexResults[key].push({expected: testSet[i].output[0], got: network.activate(testSet[i].input)[0]});
}*/

/*for (let pieceIndex in pieceIndexResults) {
    let results = pieceIndexResults[pieceIndex];
    //results.filter((a) => a.expected === 1 || a.got > 0.01);
    results.sort((a,b) => b.got[1] - a.got[1]);
    let bestOnes = results.splice(0,10);
    for (let i = 0; i < results.length; i++) {
        if (results[i].expected[1] === 1) {
            bestOnes.push(results[i]);
        }
    }
    console.log("Piece " + pieceIndex + ": ", bestOnes);
}*/


(async () => {
    const Canvas = require('canvas');
    const Image = Canvas.Image;
    const synaptic = require('synaptic');

    let doDiffs = true;
    let doCorners = true;
    let doCorrectCorners = false;
    let doDrawing = false;
    let doCreateCorrectCornerSet = false;
    let doTraining = false;
    let doGraphImages = false;

    let onlyOne = 492;

    //Diffs erstellen
    if (doDiffs) {
        console.log("DIFFS ERSTELLEN");

        for (let i = 2; i <= 501; i++) {
            if (onlyOne && i !== onlyOne) continue;
            if (fs.existsSync(__dirname + '/fixtures/pieces/piece' + i + '.jpg')) {
                try {
                    let border = await BorderFinder.findPieceBorder(__dirname + '/fixtures/pieces/piece' + i + '.jpg', {
                        debug: true,
                        threshold: 245,
                        reduction: 2,
                        returnColorPoints: true
                    });

                    let diffs = SideFinder.getPieceDiffs(border.path);

                    for (let i = 0; i < diffs.length; i++) {
                        diffs[i].point = {x: diffs[i].point.x, y: diffs[i].point.y};
                    }

                    fs.writeFileSync(__dirname + '/fixtures/pieces/piece' + i + '_border.json', JSON.stringify(border));
                    fs.writeFileSync(__dirname + '/fixtures/pieces/piece' + i + '_diffs.json', JSON.stringify(diffs));

                    console.log(i);
                } catch (e) {
                    console.log(i + " = err");
                }
            }
        }
    }

    //Korrekte Ecken ermitteln und wegschreiben
    // 22,   47,49,73,80,82,    125,    141,156,    181,189,210,223,232,246,                    326,348,353,    375,    392,397,461,            465
    // 22,46,47,49,73,80,82,113,125,128,141,156,174,181,189,210,223,    246,250,280,303,312,323,    348,    370,    378,392,397,417,421,457,461,465,470,
    if (doCorners) {
        console.log("ECKEN ERMITTELN");

        for (let i = 2; i <= 501; i++) {
            if (onlyOne && i !== onlyOne) continue;
            if (fs.existsSync(__dirname + '/fixtures/pieces/piece' + i + '_diffs.json')) {
                try {
                    let diffs = JSON.parse(fs.readFileSync(__dirname + '/fixtures/pieces/piece' + i + '_diffs.json'));
                    let possiblyCorrectOffsets = SideFinder.getPieceCornerOffsets(diffs);

                    if (possiblyCorrectOffsets !== null) {
                        fs.writeFileSync(__dirname + '/fixtures/pieces/piece' + i + '_possibleCorners.json', JSON.stringify(possiblyCorrectOffsets));
                        console.log(i);
                    } else {
                        console.log(i + " = err");
                    }
                } catch (e) {
                    console.log(i + " = err");
                }
            }
        }
    }

    //Falsch ermittelte Ecken korrigieren
    if (doCorrectCorners) {
        let corrections = {
            22: {wrong: [{x: 563, y: 247}], right: [{x: 453, y: 246}]},
            46: {wrong: [{x: 556, y: 543}], right: [{x: 289, y: 491}]},
            47: {wrong: [{x: 516, y: 220}], right: [{x: 396, y: 252}]},
            49: {wrong: [{x: 476, y: 236}], right: [{x: 362, y: 289}]},
            73: {wrong: [{x: 426, y: 167},{x: 163, y: 380},{x: 360, y: 633}], right: [{x: 307, y: 207},{x: 208, y: 480},{x: 472, y: 626}]},
            80: {wrong: [{x: 689, y: 500},{x: 214, y: 378}], right: [{x: 660, y: 366},{x: 225, y: 495}]},
            82: {wrong: [{x: 603, y: 634}], right: [{x: 622, y: 511}]},
            113: {wrong: [{x: 324, y: 347}], right: [{x: 609, y: 371}]},
            125: {wrong: [{x: 666, y: 555}], right: [{x: 658, y: 436}]},
            128: {wrong: [{x: 417, y: 206}], right: [{x: 598, y: 268}]},
            141: {wrong: [{x: 494, y: 302}, {x: 242, y: 576}], right: [{x: 562, y: 654},{x: 240, y: 563}]},
            156: {wrong: [{x: 457, y: 223}], right: [{x: 355, y: 277}]},
            174: {wrong: [{x: 682, y: 439}], right: [{x: 486, y: 232}]},
            181: {wrong: [{x: 393, y: 730}], right: [{x: 509, y: 702}]},
            189: {wrong: [{x: 387, y: 213},{x: 366, y: 734}], right: [{x: 271, y: 264},{x: 480, y: 676}]},
            210: {wrong: [{x: 365, y: 682}], right: [{x: 476, y: 655}]},
            223: {wrong: [{x: 564, y: 247}], right: [{x: 441, y: 255}]},
            246: {wrong: [{x: 434, y: 213},{x: 409, y: 711}], right: [{x: 332, y: 272},{x: 549, y: 641}]},
            250: {wrong: [{x: 499, y: 671}], right: [{x: 456, y: 340}]},
            280: {wrong: [{x: 310, y: 479}], right: [{x: 526, y: 667}]},
            303: {wrong: [{x: 515, y: 463}], right: [{x: 230, y: 494}]},
            312: {wrong: [{x: 343, y: 399}], right: [{x: 658, y: 318}]},
            323: {wrong: [{x: 336, y: 579}], right: [{x: 457, y: 683}]},
            348: {wrong: [{x: 420, y: 180}], right: [{x: 326, y: 241}]},
            370: {wrong: [{x: 281, y: 507}], right: [{x: 248, y: 619}]},
            378: {wrong: [{x: 576, y: 456}], right: [{x: 414, y: 160}]},
            392: {wrong: [{x: 201, y: 380},{x: 633, y: 622}], right: [{x: 183, y: 538}, {x: 630, y: 496}]},
            397: {wrong: [{x: 516, y: 254},{x: 412, y: 770}], right: [{x: 398, y: 282},{x: 513, y: 721}]},
            417: {wrong: [{x: 391, y: 368}], right: [{x: 409, y: 754}]},
            421: {wrong: [{x: 340, y: 441},{x: 590, y: 588}], right: [{x: 274, y: 578},{x: 670, y: 375}]},
            457: {wrong: [{x: 426, y: 333}], right: [{x: 601, y: 376}]},
            461: {wrong: [{x: 436, y: 800}], right: [{x: 544, y: 757}]},
            465: {wrong: [{x: 445, y: 231},{x: 428, y: 730}], right: [{x: 339, y: 273},{x: 521, y: 690}]},
            470: {wrong: [{x: 366, y: 364},{x: 625, y: 531}], right: [{x: 381, y: 352},{x: 574, y: 735}]}
        };

        for (let i = 2; i <= 501; i++) {
            if (onlyOne && i !== onlyOne) continue;
            if (fs.existsSync(__dirname + '/fixtures/pieces/piece' + i + '_diffs.json') && fs.existsSync(__dirname + '/fixtures/pieces/piece' + i + '_possibleCorners.json')) {
                try {
                    let border = JSON.parse(fs.readFileSync(__dirname + '/fixtures/pieces/piece' + i + '_border.json'));
                    let diffs = JSON.parse(fs.readFileSync(__dirname + '/fixtures/pieces/piece' + i + '_diffs.json'));
                    let offsets = JSON.parse(fs.readFileSync(__dirname + '/fixtures/pieces/piece' + i + '_possibleCorners.json'));

                    let getPoint = (offset) => {
                        for (let i = 0; i < diffs.length; i++) {
                            if (diffs[i].offset == offset) {
                                return diffs[i].point;
                            }
                        }
                        return null;
                    };

                    let getNearestOffset = (point) => {
                        let nearestK = null;
                        for (let k = 0; k < diffs.length; k++) {
                            let dist = Math.sqrt(Math.pow(point.x - diffs[k].point.x,2) + Math.pow(point.y - diffs[k].point.y,2));

                            if (nearestK === null || dist < nearestK.dist) {
                                nearestK = {k: k, dist: dist};
                            }
                        }
                        if (nearestK !== null) {
                            return diffs[nearestK.k].offset;
                        }
                        return null;
                    };

                    let invalidOffsets = [];
                    if (typeof corrections[i] !== 'undefined') {
                        for (let l = 0; l < corrections[i].wrong.length; l++) {
                            let lowestK = null;
                            for (k = 0; k < offsets.length; k++) {
                                let point = getPoint(offsets[k]);
                                let dist = Math.sqrt(Math.pow(point.x - (corrections[i].wrong[l].x - border.boundingBox.left),2) + Math.pow(point.y - (corrections[i].wrong[l].y - border.boundingBox.top),2));

                                if (lowestK === null || dist < lowestK.dist) {
                                    lowestK = {k: k, dist: dist};
                                }
                            }
                            if (lowestK !== null) {
                                invalidOffsets.push(offsets[lowestK.k]);
                            }
                        }
                    }

                    let correctedOffsets = [];
                    for (let j = 0; j < offsets.length; j++) {
                        let isInvalid = false;
                        for (let k = 0; k < invalidOffsets.length; k++) {
                            if (invalidOffsets[k] == offsets[j]) {
                                isInvalid = true;
                                break;
                            }
                        }
                        if (!isInvalid) {
                            correctedOffsets.push(offsets[j]);
                        }
                    }

                    if (typeof corrections[i] !== 'undefined') {
                        for (let j = 0; j < corrections[i].right.length; j++) {
                            let offset = getNearestOffset({x: corrections[i].right[j].x - border.boundingBox.left, y: corrections[i].right[j].y - border.boundingBox.top});
                            if (offset !== null) {
                                correctedOffsets.push(offset);
                            }
                        }
                    }

                    fs.writeFileSync(__dirname + '/fixtures/pieces/piece' + i + '_possibleCorners_corrected.json', JSON.stringify(correctedOffsets));

                    console.log(i);
                } catch (err) {
                    console.log(i + ' .. err', err);
                }
            }
        }
    }

    //Positionen/Indizes zu den möglichen Ecken ermitteln
    if (doDrawing) {
        for (let i = 2; i <= 501; i++) {
            if (onlyOne && i !== onlyOne) continue;
            if (fs.existsSync(__dirname + '/fixtures/pieces/piece' + i + '_diffs.json') && fs.existsSync(__dirname + '/fixtures/pieces/piece' + i + '_possibleCorners.json')) {
                try {
                    let border = JSON.parse(fs.readFileSync(__dirname + '/fixtures/pieces/piece' + i + '_border.json'));
                    let diffs = JSON.parse(fs.readFileSync(__dirname + '/fixtures/pieces/piece' + i + '_diffs.json'));
                    let offsets = JSON.parse(fs.readFileSync(__dirname + '/fixtures/pieces/piece' + i + '_possibleCorners_corrected.json'));

                    let offsetPositions = [];

                    for (let i = 0; i < offsets.length; i++) {
                        for (let j = 0; j < diffs.length; j++) {
                            if (diffs[j].offset == offsets[i]) {
                                offsetPositions.push({index: j, point: diffs[j].point});
                            }
                        }
                    }
                    if (offsetPositions.length === 4) {
                        fs.writeFileSync(__dirname + '/fixtures/pieces/piece' + i + '_possibleCornerPositions.json', JSON.stringify(offsetPositions));

                        let imageData = fs.readFileSync(__dirname + '/fixtures/pieces/piece' + i + '.jpg.2.step2e.png');
                        let img = new Image;
                        img.src = imageData;

                        const canvas = new Canvas(img.width, img.height);
                        const context = canvas.getContext('2d');
                        context.drawImage(img, 0, 0, img.width, img.height);

                        for (let i = 0; i < offsetPositions.length; i++) {
                            let radius = 10;
                            context.beginPath();
                            context.arc(offsetPositions[i].point.x + border.boundingBox.left, offsetPositions[i].point.y + border.boundingBox.top, radius, 0, 2 * Math.PI, false);
                            context.fillStyle = 'rgb(255,0,0)';
                            context.fill();
                        }

                        for (let i = 0; i < diffs.length; i++) {
                            let radius = 1;
                            context.beginPath();
                            context.arc(diffs[i].point.x + border.boundingBox.left, diffs[i].point.y + border.boundingBox.top, radius, 0, 2 * Math.PI, false);
                            context.fillStyle = 'rgb(0,255,0)';
                            context.fill();
                        }

                        let out = fs.createWriteStream(__dirname + '/fixtures/pieces/piece' + i + '_foundPoints.jpg');
                        let stream = canvas.pngStream();

                        stream.on('data', function (chunk) {
                            out.write(chunk);
                        });

                        console.log(i);
                    } else {
                        console.log(i + " = err");
                    }
                } catch (e) {
                    console.log(i + " = err");
                }
            }
        }
    }

    if (doCreateCorrectCornerSet) {
        let set = {};
        let setSimple = {};
        for (let i = 2; i <= 501; i++) {
            if (onlyOne && i !== onlyOne) continue;
            if (fs.existsSync(__dirname + '/fixtures/pieces/piece' + i + '_diffs.json') && fs.existsSync(__dirname + '/fixtures/pieces/piece' + i + '_possibleCorners_corrected.json')) {
                let diffs = JSON.parse(fs.readFileSync(__dirname + '/fixtures/pieces/piece' + i + '_diffs.json'));
                let offsets = JSON.parse(fs.readFileSync(__dirname + '/fixtures/pieces/piece' + i + '_possibleCorners_corrected.json'));
                let offsetsWrong = JSON.parse(fs.readFileSync(__dirname + '/fixtures/pieces/piece' + i + '_possibleCorners.json'));

                let request = [];
                let response = [];
                let wrong = [];

                for (let i = 0; i < diffs.length; i++) {
                    request.push(diffs[i].diff);

                    let found = false;
                    for (let j = 0; j < offsets.length; j++) {
                        if (diffs[i].offset == offsets[j]) {
                            found = true;
                        }
                    }

                    response.push(found ? 1 : 0);

                    let foundWrong = false;
                    for (let j = 0; j < offsetsWrong.length; j++) {
                        if (diffs[i].offset == offsetsWrong[j]) {
                            foundWrong = true;
                        }
                    }

                    wrong.push(foundWrong ? 1 : 0);
                }

                set[i] = {input: request, output: response, wrongOutput: wrong};

                ////////////////////////////////////////////////////////////////////////////////////////////////

                let requestSimple = [];
                let responseSimple = [];
                let factor = 20;
                for (let j = 0; j < 2000; j += factor) {
                    let max = 0;
                    let min = 0;
                    for (let k = j + 2000 - factor; k <= j + 2000 + factor; k++) {
                        max = Math.max(max, diffs[k % 2000].diff);
                        min = Math.min(min, diffs[k % 2000].diff);
                    }
                    //let diff = diffSum / (factor * 2 + 1);
                    let diff = Math.abs(max) > Math.abs(min) ? max : min;

                    requestSimple.push(diff);
                }

                let indices = [];
                for (let i = 0; i < 2000; i++) {
                    let found = false;
                    for (let j = 0; j < offsets.length; j++) {
                        if (diffs[i].offset == offsets[j]) {
                            found = true;
                        }
                    }

                    if (found) {
                        indices.push(i);
                    }
                }

                let newIndices = [];
                for (let j = 0; j < indices.length; j++) {
                    newIndices.push(Math.round((indices[j] + 2000 + factor / 2) / factor) % (2000 / factor));
                }

                for (let j = 0; j < 2000 / factor; j++) {
                    let found = false;
                    for (let k = 0; k < newIndices.length; k++) {
                        if (j == newIndices[k]) {
                            found = true;
                            break;
                        }
                    }

                    responseSimple.push(found ? 1 : 0);
                }

                setSimple[i] = {input: requestSimple, output: responseSimple};
            }
        }

        fs.writeFileSync(__dirname + '/fixtures/pieces/cornerSolution_simple.json', JSON.stringify(setSimple));
    }

    if (doTraining) {
        let trainingSet = JSON.parse(fs.readFileSync(__dirname + '/fixtures/pieces/cornerSolution_simple.json'));

        let training = [];
        let compare = [];

        for (let i = 2; i <= 501; i++) {
            if (onlyOne && i !== onlyOne) continue;

            if ([326, 375].indexOf(i) > -1) continue; //Falsche Bilderkennung nicht zum Trainieren verwenden

            if (i < 417 || i > 461) {
                training.push(trainingSet[i]);
            } else {
                compare.push(trainingSet[i]);
            }
        }

        let network = new synaptic.Architect.Perceptron(100, 66, 4, 100);
        let trainer = new synaptic.Trainer(network);

        console.log("training started");
        trainer.train(training, {
            rate: .2,
            iterations: 100,
            error: 0.00000000005,
            shuffle: true,
            log: 1
        });
    }

    if (doGraphImages) {
        let trainingSet = JSON.parse(fs.readFileSync(__dirname + '/fixtures/pieces/cornerSolution.json'));
        for (let i = 2; i <= 501; i++) {
            if (typeof trainingSet[i] === 'undefined') continue;
            if (onlyOne && i !== onlyOne) continue;

            const canvas = new Canvas(800, 200);
            const context = canvas.getContext('2d');

            //Corners Wrong
            context.strokeStyle = 'rgb(255,255,0)';
            context.beginPath();
            for (let j = 0; j < trainingSet[i].wrongOutput.length; j++) {
                if (trainingSet[i].wrongOutput[j] == 0) continue;

                context.moveTo(j * 0.4, 0);
                context.lineTo(j * 0.4, 200);
            }
            context.stroke();

            //Corners
            context.strokeStyle = 'rgb(255,0,0)';
            context.beginPath();
            for (let j = 0; j < trainingSet[i].output.length; j++) {
                if (trainingSet[i].output[j] == 0) continue;

                context.moveTo(j * 0.4, 0);
                context.lineTo(j * 0.4, 200);
            }
            context.stroke();

            //Detailed line
            context.strokeStyle = 'rgb(0,0,0)';
            context.beginPath();
            for (let j = 0; j < trainingSet[i].input.length; j++) {
                let diff = trainingSet[i].input[j];

                if (j === 0) {
                    context.moveTo(0, diff + 100);
                }

                context.lineTo(j * 0.4, diff + 100);
            }
            context.stroke();

            let out = fs.createWriteStream(__dirname + '/fixtures/pieces/piece' + i + '_diff.jpg');
            let stream = canvas.pngStream();

            stream.on('data', function (chunk) {
                out.write(chunk);
            });

            console.log(i);
        }

        let trainingSetSimple = JSON.parse(fs.readFileSync(__dirname + '/fixtures/pieces/cornerSolution_simple.json'));
        for (let i = 2; i <= 501; i++) {
            if (typeof trainingSetSimple[i] === 'undefined') continue;
            if (onlyOne && i !== onlyOne) continue;

            const canvas = new Canvas(800, 200);
            const context = canvas.getContext('2d');

            //Corners
            context.strokeStyle = 'rgb(255,0,0)';
            context.beginPath();
            for (let j = 0; j < trainingSetSimple[i].output.length; j++) {
                if (trainingSetSimple[i].output[j] == 0) continue;

                context.moveTo(j * 8, 0);
                context.lineTo(j * 8, 200);
            }
            context.stroke();

            //Detailed line
            context.strokeStyle = 'rgb(0,0,0)';
            context.beginPath();
            for (let j = 0; j < trainingSetSimple[i].input.length; j++) {
                let diff = trainingSetSimple[i].input[j];

                if (j === 0) {
                    context.moveTo(0, diff + 100);
                }

                context.lineTo(j * 8, diff + 100);
            }
            context.stroke();

            let out = fs.createWriteStream(__dirname + '/fixtures/pieces/piece' + i + '_diff_simple.jpg');
            let stream = canvas.pngStream();

            stream.on('data', function (chunk) {
                out.write(chunk);
            });

            console.log(i);
        }
    }
})();